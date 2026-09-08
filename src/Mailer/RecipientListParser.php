<?php

namespace App\Mailer;

use InvalidArgumentException;
use Symfony\Component\Mime\Address;
use Throwable;

final class RecipientListParser
{
    /**
     * @return list<Address>
     */
    public function parse(string $raw): array
    {
        $addresses = [];
        foreach ($this->tokenize($raw) as $token) {
            try {
                $addresses[] = Address::create($token);
            } catch (Throwable $e) {
                throw new InvalidArgumentException('Invalid recipient: '.$token, 0, $e);
            }
        }

        return $addresses;
    }

    /**
     * @param list<Address> $addresses
     */
    public function formatList(array $addresses): string
    {
        return implode('; ', array_map($this->format(...), $addresses));
    }

    public function format(Address $address): string
    {
        $name = trim($address->getName());
        $email = $address->getAddress();

        return $name !== '' ? $name.' <'.$email.'>' : $email;
    }

    /**
     * @param list<Address> $addresses
     *
     * @return list<string>
     */
    public function mailboxSet(array $addresses): array
    {
        $set = [];
        foreach ($addresses as $address) {
            $set[strtolower($address->getAddress())] = true;
        }
        ksort($set);

        return array_keys($set);
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $tokens = [];
        $buffer = '';
        $inQuotes = false;
        $angleDepth = 0;
        $length = strlen($raw);

        for ($i = 0; $i < $length; ++$i) {
            $char = $raw[$i];
            if ($char === '"' && ($i === 0 || $raw[$i - 1] !== '\\')) {
                $inQuotes = !$inQuotes;
                $buffer .= $char;
                continue;
            }
            if (!$inQuotes) {
                if ($char === '<') {
                    ++$angleDepth;
                } elseif ($char === '>' && $angleDepth > 0) {
                    --$angleDepth;
                } elseif ($char === ';' && $angleDepth === 0) {
                    $token = trim($buffer);
                    if ($token !== '') {
                        $tokens[] = $token;
                    }
                    $buffer = '';
                    continue;
                }
            }
            $buffer .= $char;
        }

        $token = trim($buffer);
        if ($token !== '') {
            $tokens[] = $token;
        }

        return $tokens;
    }
}
