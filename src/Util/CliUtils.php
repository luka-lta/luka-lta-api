<?php

declare(strict_types=1);

namespace LukaLtaApi\Util;

use Stringable;

class CliUtils
{
    private bool $isInteractiveOut;
    private bool $isInteractiveErr;

    /** @codeCoverageIgnore */
    public function isInteractiveOut(): bool
    {
        if (!isset($this->isInteractiveOut)) {
            $this->isInteractiveOut = stream_isatty(STDOUT);
        }

        return $this->isInteractiveOut;
    }

    /** @codeCoverageIgnore */
    public function isInteractiveErr(): bool
    {
        if (!isset($this->isInteractiveErr)) {
            $this->isInteractiveErr = stream_isatty(STDERR);
        }

        return $this->isInteractiveErr;
    }

    /** @codeCoverageIgnore */
    public function writeErr(string|Stringable $content): void
    {
        fwrite(STDERR, (string)$content);
    }

    /**
     * @codeCoverageIgnore
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @noinspection PhpComposerExtensionStubsInspection
     */
    public static function promptSecret(string $prompt): string
    {
        $password = '';
        readline_callback_handler_install($prompt, static function () {});
        $in = STDIN;
        stream_set_blocking($in, false);

        // Faint, underline, overline
        echo "\x1B[2;4;53m";
        $doRead = true;

        while (true) {
            $inputChar = fgetc($in);

            // To not run a super hot infinite loop
            if ($inputChar === false) {
                usleep(100000);
                continue;
            }

            // stop on new line or EOF/^D
            if ($inputChar === "\r" || $inputChar === "\n" || $inputChar === "\x04") {
                break;
            }

            // Backspace to delete
            if ($inputChar === "\x7F") {
                if ($password === '') {
                    // BEL
                    echo "\x07";
                }

                if ($password !== '') {
                    // Remove last char
                    $password = substr($password, 0, -1);

                    // Move cursor 1 left and erase until EOL
                    echo "\x1B[1D\x1B[0K";
                }

                continue;
            }

            // Escape symbol. Pasting results gives "\033[200~pasted_value\033[201~"
            if ($inputChar === "\033") {
                $doRead = false;
                continue;
            }

            if (!$doRead && ($inputChar === '~' || $inputChar === 'm')) {
                $doRead = true;
                continue;
            }

            if ($doRead) {
                echo '*';
                $password .= $inputChar;
            }
        }

        // Reset and clear the line
        echo "\x1B[0m\x1B[2K";
        readline_callback_handler_remove();
        return $password;
    }
}
