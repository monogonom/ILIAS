<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use Whoops\Exception\Formatter;

/**
 * A Whoops error handler that prints the same content as the PrettyPageHandler but as plain text.
 * This is used for better coexistence with xdebug, see #16627.
 * @author Richard Klees <richard.klees@concepts-and-training.de>
 */
class ilPlainTextHandler extends \Whoops\Handler\PlainTextHandler
{
    protected const KEY_SPACE = 25;

    /** @var list<string> */
    private array $exclusion_list = [];

    /**
     * @param list<string> $exclusion_list
     */
    public function withExclusionList(array $exclusion_list): self
    {
        $clone = clone $this;
        $clone->exclusion_list = $exclusion_list;
        return $clone;
    }

    private function stripNullBytes(string $ret): string
    {
        return str_replace("\0", '', $ret);
    }

    public function generateResponse(): string
    {
        return $this->getPlainTextExceptionOutput() . $this->tablesContent() . "\n";
    }

    protected function getSimpleExceptionOutput(Throwable $exception): string
    {
        return sprintf(
            '%s: %s in file %s on line %d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
    }

    /**
     * Get a short info about the exception.
     */
    protected function getPlainTextExceptionOutput(bool $with_previous = true): string
    {
        $message = Formatter::formatExceptionPlain($this->getInspector());

        if ($with_previous) {
            $exception = $this->getInspector()->getException();
            $previous = $exception->getPrevious();
            while ($previous) {
                $message .= "\n\nCaused by\n" . $this->getSimpleExceptionOutput($previous);
                $previous = $previous->getPrevious();
            }
        }

        return $message;
    }

    /**
     * Get the header for the page.
     */
    protected function tablesContent(): string
    {
        $ret = '';
        foreach ($this->tables() as $title => $content) {
            $ret .= "\n\n-- $title --\n\n";
            if (count($content) > 0) {
                foreach ($content as $key => $value) {
                    $key = str_pad((string) $key, self::KEY_SPACE);

                    // indent multiline values, first print_r, split in lines,
                    // indent all but first line, then implode again.
                    $first = true;
                    $indentation = str_pad('', self::KEY_SPACE);
                    $value = implode(
                        "\n",
                        array_map(
                            static function ($line) use (&$first, $indentation): string {
                                if ($first) {
                                    $first = false;
                                    return $line;
                                }
                                return $indentation . $line;
                            },
                            explode("\n", print_r($value, true))
                        )
                    );

                    $ret .= "$key: $value\n";
                }
            } else {
                $ret .= "empty\n";
            }
        }

        return $this->stripNullBytes($ret);
    }

    /**
     * Get the tables that should be rendered.
     */
    protected function tables(): array
    {
        $post = $_POST;
        $server = $_SERVER;

        $post = $this->hideSensitiveData($post);
        $server = $this->hideSensitiveData($server);
        $server = $this->shortenPHPSessionId($server);

        return [
            'GET Data' => $_GET,
            'POST Data' => $post,
            'Files' => $_FILES,
            'Cookies' => $_COOKIE,
            'Session' => $_SESSION ?? [],
            'Server/Request Data' => $server,
            'Environment Variables' => $_ENV,
        ];
    }

    /**
     * @param array<string, mixed> $super_global
     * @return array<string, mixed>
     */
    private function hideSensitiveData(array $super_global): array
    {
        foreach ($this->exclusion_list as $parameter) {
            if (isset($super_global[$parameter])) {
                $super_global[$parameter] = 'REMOVED FOR SECURITY';
            }

            if (isset($super_global['post_vars'][$parameter])) {
                $super_global['post_vars'][$parameter] = 'REMOVED FOR SECURITY';
            }
        }

        return $super_global;
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, mixed>
     */
    private function shortenPHPSessionId(array $server): array
    {
        $cookie_content = $server['HTTP_COOKIE'];
        $cookie_content = explode(';', $cookie_content);

        foreach ($cookie_content as $key => $content) {
            $content_array = explode('=', $content);
            if (trim($content_array[0]) === session_name()) {
                $content_array[1] = substr($content_array[1], 0, 5) . ' (SHORTENED FOR SECURITY)';
                $cookie_content[$key] = implode('=', $content_array);
            }
        }

        $server['HTTP_COOKIE'] = implode(';', $cookie_content);

        return $server;
    }
}
