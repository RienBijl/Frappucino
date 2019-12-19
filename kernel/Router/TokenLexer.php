<?php

namespace Kernel\Router;

use Kernel\Exceptions\LexerException;
use Kernel\Util\Validator;

class TokenLexer
{

    private ?array $tokens;
    private array $namespace = [];
    private array $results = [];

    public function __construct(?array $tokens)
    {
        $this->tokens = $tokens;
        $this->findCombinations();
    }

    /**
     * Get the lexer results
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Find all combinations
     * @return array
     * @throws \Exception
     */
    private function findCombinations(): array
    {
        $count = 0;
        foreach ($this->tokens ?? [] as $token) {
            $count++;
            if ($this->validateToken($token)) {
                $this->lex($token, $count);
            }
        }

        return [];
    }

    /**
     * Lex a specific token
     * @param array $token
     * @param int $count
     * @throws \Exception
     */
    private function lex(array $token, int $count)
    {
        if ($token[0] === T_NAMESPACE && $this->namespace !== null) {
            $this->namespace = $this->deriveNamespace($count);
        }
        if ($token[0] === T_DOC_COMMENT) {
            $route = $this->getRouteDeclaration($token);
            $function = $this->getFirstFunction($count);
            $this->results[] = [
                "route" => $route,
                "namespace" => implode("\\", $this->namespace),
                "function" => $function
            ];
        }
    }

    /**
     * Get a new route declaration
     * @param array $token
     * @return array|null
     * @throws LexerException
     */
    private function getRouteDeclaration(array $token): ?array
    {
        $lines = explode("\n", $token[1]);

        foreach ($lines as $line) {
            $line = explode(" ", trim($line));
            if ($line[0] === "@Route") {
                if (($linecount = count($line)) === 3) {
                    if (Validator::isHttpMethod($line[1])) {
                        return [$line[1], $line[2]];
                    } else {
                        throw new LexerException($line[1] . " is not an HTTP method, allowed: GET, POST, DELETE, PUT");
                    }
                } else {
                    throw new LexerException("Expected 2 arguments for @Route annotation, found " . $linecount, $token);
                }
            }
        }
        return null;
    }

    /**
     * Get the first function after a specific token
     * @param int $start
     * @return string|null
     * @throws LexerException
     */
    private function getFirstFunction(int $start): ?string
    {
        $lastToken = null;
        foreach ($this->tokens as $k => $token) {
            if ($k < $start) {
                continue;
            }
            if (is_string($token)) {
                continue;
            }

            if ($this->expect($token[0], [T_PUBLIC, T_FUNCTION, T_STRING, T_WHITESPACE, T_DOC_COMMENT])) {
                if ($token[0] === T_STRING) {
                    return $token[1];
                    break;
                }
            }
        }
        return null;
    }

    /**
     * Derive namespace from tokenset
     * @param int $start
     * @return array
     * @throws LexerException
     */
    private function deriveNamespace(int $start): array
    {
        $namespacePath = [];
        foreach ($this->tokens as $k => $token) {
            if ($k < $start) {
                continue;
            }
            if (is_string($token) || $token === ";") {
                break;
            }
            if ($this->expect($token[0], [T_STRING, T_NS_SEPARATOR, T_WHITESPACE]) && $token[0] === T_STRING) {
                $namespacePath[] = $token[1];
            }
        }
        return $namespacePath;
    }

    /**
     * @param array|string|null $token
     * @return bool
     */
    private function validateToken($token)
    {
        return $token !== null && is_array($token) && count($token) > 2;
    }

    /**
     * Expect a tokenset
     * @param int $token
     * @param array $expectedTokens
     * @param bool $throwException
     * @return bool
     * @throws LexerException
     */
    public static function expect(int $token, array $expectedTokens, bool $throwException = true): bool
    {
        $allowed = false;
        foreach ($expectedTokens as $expectedToken) {
            if ($expectedToken === $token) {
                $allowed = true;
            }
        }
        if (!$allowed && $throwException) {
            $tokens = [];
            foreach ($expectedTokens as $expectedToken) {
                $tokens[] = token_name($expectedToken);
            }
            throw new LexerException("Expecting " . implode(", ", $tokens) . ' found ' . token_name($token));
        }
        return $allowed;
    }
}
