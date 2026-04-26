#!/usr/bin/env bash

set -euo pipefail

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== Myanmar Payments Release Script ===${NC}\n"

fail() {
    echo -e "${RED}✗ $1${NC}"
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Required command not found: $1"
}

prompt_or_fail_for_message() {
    local current_value="$1"
    local prompt_label="$2"
    local default_value="$3"
    local required_in_non_interactive="$4"
    local resolved_value="$current_value"

    if [ -n "$resolved_value" ]; then
        printf '%s' "$resolved_value"
        return 0
    fi

    if [ -t 0 ]; then
        if [ -n "$default_value" ]; then
            echo -e "${YELLOW}${prompt_label}${NC} (leave blank to use default: ${default_value}):"
        else
            echo -e "${YELLOW}${prompt_label}${NC}:"
        fi

        read -r resolved_value

        if [ -z "$resolved_value" ]; then
            resolved_value="$default_value"
        fi

        [ -n "$resolved_value" ] || fail "Commit message is required."
        printf '%s' "$resolved_value"
        return 0
    fi

    if [ "$required_in_non_interactive" = "true" ]; then
        fail "${prompt_label} is required in non-interactive mode."
    fi

    resolved_value="$default_value"
    echo -e "${YELLOW}No interactive input detected. Using default commit message:${NC} ${resolved_value}" >&2
    printf '%s' "$resolved_value"
}

require_command git
require_command composer

RELEASE_COMMIT_MSG_INPUT="${RELEASE_COMMIT_MESSAGE:-}"
SAVE_COMMIT_MSG_INPUT="${RELEASE_SAVE_COMMIT_MESSAGE:-}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        -m|--message)
            shift
            [[ $# -gt 0 ]] || fail "Missing value for release commit message option."
            RELEASE_COMMIT_MSG_INPUT="$1"
            ;;
        --save-message)
            shift
            [[ $# -gt 0 ]] || fail "Missing value for save commit message option."
            SAVE_COMMIT_MSG_INPUT="$1"
            ;;
        *)
            fail "Unknown argument: $1"
            ;;
    esac

    shift
done

if [ ! -f VERSION ]; then
    fail "VERSION file is missing."
fi

CURRENT_BRANCH=$(git branch --show-current)

if [ "$CURRENT_BRANCH" != "main" ]; then
    fail "Release must be created from main. Current branch: ${CURRENT_BRANCH}"
fi

echo -e "${BLUE}Syncing with latest origin/main...${NC}"
git pull --rebase origin main

# Get current version from VERSION file
CURRENT_VERSION=$(tr -d '[:space:]' < VERSION)

if [[ ! "$CURRENT_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    fail "VERSION must use semantic version format like 1.2.3."
fi

# The current VERSION is what we release
TAG="v${CURRENT_VERSION}"

echo -e "${YELLOW}Releasing version:${NC} ${CURRENT_VERSION}"
echo -e "${YELLOW}Release tag:${NC} ${TAG}"
echo

# Check if tag already exists
if git rev-parse "$TAG" >/dev/null 2>&1; then
    fail "Tag ${TAG} already exists!"
fi

# Show current git context before release checks
echo -e "${BLUE}Current git status:${NC}"
git status --short --branch

echo -e "\n${BLUE}Current git diff:${NC}"
git diff --staged
git diff

echo -e "\n${BLUE}Recent commits:${NC}"
git log --oneline -5
echo

# Save any local changes before release so the tag is created from committed work
if [ -n "$(git status --porcelain)" ]; then
    DEFAULT_SAVE_COMMIT_MSG="chore: prepare release ${CURRENT_VERSION}"
    SAVE_COMMIT_MSG=$(prompt_or_fail_for_message "$SAVE_COMMIT_MSG_INPUT" "Enter save commit message before release" "$DEFAULT_SAVE_COMMIT_MSG" true)

    echo -e "${YELLOW}Save commit message before release:${NC} ${SAVE_COMMIT_MSG}\n"
    echo -e "${BLUE}Staging all tracked and untracked changes...${NC}"
    git add -A

    echo -e "${BLUE}Creating save commit before release...${NC}"
    git commit -m "$SAVE_COMMIT_MSG"

    echo -e "${BLUE}Pushing save commit to GitHub...${NC}"
    git push origin main

    echo -e "\n${BLUE}Git state after save commit push:${NC}"
    git status --short --branch
    echo
fi

# Run tests
echo -e "\n${BLUE}Running tests...${NC}"
if ! composer test; then
    fail "Tests failed! Release aborted."
fi
echo -e "${GREEN}✓ Tests passed!${NC}"

# Run static analysis
echo -e "\n${BLUE}Running PHPStan analysis...${NC}"
if ! composer analyse; then
    fail "PHPStan analysis failed! Release aborted."
fi
echo -e "${GREEN}✓ Analysis passed!${NC}\n"

# Resolve release commit message without requiring an interactive shell
DEFAULT_RELEASE_COMMIT_MSG="chore: release ${CURRENT_VERSION}"
RELEASE_COMMIT_MSG=$(prompt_or_fail_for_message "$RELEASE_COMMIT_MSG_INPUT" "Enter release commit message" "$DEFAULT_RELEASE_COMMIT_MSG" false)

echo -e "${YELLOW}Release commit message:${NC} ${RELEASE_COMMIT_MSG}\n"

# No VERSION change needed — tag points to current commit as-is
git commit --allow-empty -m "${RELEASE_COMMIT_MSG}"

# Create tag
echo -e "${BLUE}Creating tag ${TAG}...${NC}"
git tag "$TAG"

# Push commit and tag to trigger workflow
echo -e "${BLUE}Pushing release to GitHub...${NC}"
if ! git push origin main; then
    echo -e "${YELLOW}⚠ Push rejected (remote has diverged). Rebasing onto origin/main...${NC}"

    # Move the tag off the commit temporarily so it survives the rebase
    git tag -d "$TAG"

    if ! git pull --rebase origin main; then
        fail "Rebase failed! Resolve conflicts manually, then re-run the script."
    fi
    echo -e "${GREEN}✓ Rebase succeeded.${NC}"

    # Re-apply tag to the new HEAD after rebase
    git tag "$TAG"

    if ! git push origin main; then
        fail "Push failed after rebase. Check your permissions or network and retry."
    fi
fi

if ! git push origin "$TAG"; then
    fail "Failed to push tag ${TAG}. It may already exist on the remote."
fi

echo -e "\n${BLUE}Git state after release push:${NC}"
git status --short --branch
echo -e "\n${BLUE}Recent commits after release:${NC}"
git log --oneline -5

echo -e "\n${GREEN}✓ Release deployed successfully!${NC}"
echo -e "${BLUE}=== Release Summary ===${NC}"
echo -e "  Released version: ${CURRENT_VERSION}"
echo -e "  Tag: ${TAG}"
echo -e "  VERSION file remains: ${CURRENT_VERSION}"
echo -e "\n${YELLOW}GitHub Actions is now:${NC}"
echo "  1. Creating a GitHub Release for ${TAG}"
echo -e "\n${BLUE}View the release:${NC} https://github.com/hakhant21/myanmar-payments/releases/tag/${TAG}"
echo -e "${BLUE}View workflow:${NC} https://github.com/hakhant21/myanmar-payments/actions\n"
