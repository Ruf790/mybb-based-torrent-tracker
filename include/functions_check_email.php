<?php

declare(strict_types=1);

function check_email(string $email): bool
{
    return (bool)preg_match('#^[a-z0-9.!\\#$%&\'*+-/=?^_`{|}~]+@([0-9.]+|([^\\s\'"<>]+\\.+[a-z]{2,6}))$#si', $email);
}
