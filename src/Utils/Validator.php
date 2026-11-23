<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Utils;

use App\Entity\User\User;
use Symfony\Component\Console\Exception\InvalidArgumentException;

/**
 * This class is used to provide an example of integrating simple classes as
 * services into a Symfony application.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class Validator
{
    public function validateUsername(?string $username): string
    {
        if (empty($username)) {
            throw new InvalidArgumentException('The username can not be empty.');
        }

        if (1 !== preg_match('/^[a-z_]+$/', $username)) {
            throw new InvalidArgumentException('The username must contain only lowercase latin characters and underscores.');
        }

        return $username;
    }

    public function validatePassword(?string $plainPassword): string
    {
        if (empty($plainPassword)) {
            throw new InvalidArgumentException('The password can not be empty.');
        }

        if (mb_strlen(trim($plainPassword)) < User::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(sprintf('The password must be at least %d characters long.', User::MIN_PASSWORD_LENGTH));
        }

        return $plainPassword;
    }

    public function validateEmail(?string $email): string
    {
        if (empty($email)) {
            throw new InvalidArgumentException('The email can not be empty.');
        }

        if (false === mb_strpos($email, '@')) {
            throw new InvalidArgumentException('The email should look like a real email.');
        }

        return $email;
    }

    public function validateFullName(?string $fullName): string
    {
        if (empty($fullName)) {
            throw new InvalidArgumentException('The full name can not be empty.');
        }

        return $fullName;
    }
}
