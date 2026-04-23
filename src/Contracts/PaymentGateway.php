<?php

declare(strict_types=1);

namespace Hakhant\Payments\Contracts;

interface PaymentGateway extends CanInitiatePayment, CanQueryPayment {}
