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

require_command git
require_command composer

COMMIT_MSG_INPUT="${RELEASE_COMMIT_MESSAGE:-}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        -m|--message)
            shift
            [[ $# -gt 0 ]] || fail "Missing value for commit message option."
            COMMIT_MSG_INPUT="$1"
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

# Get current version from VERSION file
CURRENT_VERSION=$(tr -d '[:space:]' < VERSION)

if [[ ! "$CURRENT_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    fail "VERSION must use semantic version format like 1.2.3."
fi

# The current VERSION is what we release
TAG="v${CURRENT_VERSION}"

# Calculate next version for post-release bump (0.0.1)
IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT_VERSION"
NEXT_PATCH=$((PATCH + 1))
NEXT_VERSION="${MAJOR}.${MINOR}.${NEXT_PATCH}"

echo -e "${YELLOW}Releasing version:${NC} ${CURRENT_VERSION}"
echo -e "${YELLOW}Release tag:${NC} ${TAG}"
echo -e "${YELLOW}Next version after release:${NC} ${NEXT_VERSION}\n"

# Check if tag already exists
if git rev-parse "$TAG" >/dev/null 2>&1; then
    fail "Tag ${TAG} already exists!"
fi

# Verify git status
if [ -n "$(git status --porcelain)" ]; then
    echo -e "${RED}✗ Working directory is not clean!${NC}"
    echo "Please commit or stash your changes before releasing."
    git status
    exit 1
fi

CURRENT_BRANCH=$(git branch --show-current)

if [ "$CURRENT_BRANCH" != "main" ]; then
    fail "Release must be created from main. Current branch: ${CURRENT_BRANCH}"
fi

# Rebase onto latest main to avoid push conflicts later
echo -e "${BLUE}Rebasing onto latest origin/main...${NC}"
git pull --rebase origin main

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

# Resolve commit message without requiring an interactive shell
DEFAULT_COMMIT_MSG="chore: release ${CURRENT_VERSION}"

if [ -n "$COMMIT_MSG_INPUT" ]; then
    COMMIT_MSG="$COMMIT_MSG_INPUT"
elif [ -t 0 ]; then
    echo -e "${YELLOW}Enter release commit message${NC} (leave blank to use default: ${DEFAULT_COMMIT_MSG}):"
    read -r USER_COMMIT_MSG
    COMMIT_MSG="${USER_COMMIT_MSG:-$DEFAULT_COMMIT_MSG}"
else
    COMMIT_MSG="$DEFAULT_COMMIT_MSG"
    echo -e "${YELLOW}No interactive input detected. Using default commit message:${NC} ${COMMIT_MSG}"
fi

echo -e "${YELLOW}Commit message:${NC} ${COMMIT_MSG}\n"

# No VERSION change needed — tag points to current commit as-is
git commit --allow-empty -m "${COMMIT_MSG}"

# Create tag
echo -e "${BLUE}Creating tag ${TAG}...${NC}"
git tag "$TAG"

# Push commit and tag to trigger workflow
echo -e "${BLUE}Pushing to GitHub...${NC}"
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

echo -e "\n${GREEN}✓ Release deployed successfully!${NC}"
echo -e "${BLUE}=== Release Summary ===${NC}"
echo -e "  Released version: ${CURRENT_VERSION}"
echo -e "  Tag: ${TAG}"
echo -e "  Next version in VERSION file: ${NEXT_VERSION} (set by GitHub Actions)"
echo -e "\n${YELLOW}GitHub Actions is now:${NC}"
echo "  1. Creating a GitHub Release for ${TAG}"
echo "  2. Auto-bumping VERSION to ${NEXT_VERSION} on main"
echo -e "\n${BLUE}View the release:${NC} https://github.com/hakhant21/myanmar-payments/releases/tag/${TAG}"
echo -e "${BLUE}View workflow:${NC} https://github.com/hakhant21/myanmar-payments/actions\n"
