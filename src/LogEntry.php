<?php
declare(strict_types=1);

namespace UCRM\Logging;

use MVQN\Collections\Collection;
use MVQN\Dynamics\Annotations\AcceptsAnnotation as Accepts;
use MVQN\Dynamics\AutoObject;

use \Exception;
use \DateTimeImmutable;
use \DateTimeZone;

/**
 * Class LogEntry
 *
 * @package MVQN\UCRM\Plugins
 * @author Ryan Spaeth <rspaeth@spaethtech.com>
 *
 * @method string getTimestamp()
 * @method string getChannel()
 * @method int getLevel()
 * @method string getLevelName()
 * @method string getMessage()
 * @method string|null getContext()
 * @method string|null getExtra()
 */
class LogEntry extends AutoObject
{
    protected const REGEX_ENTRY_HEADER = '/^\[([\w|\-]* [\w|\:|\.\+]*)](?: \[(?:(\w*)\.)?(\w*)\])? (.*)$/m';

    /** @const string The format to be used as the timestamp. */
    public const TIMESTAMP_FORMAT_DATETIME = "Y-m-d H:i:s.uP";

    /**
     * @var string
     */
    protected $timestamp;

    /** @noinspection PhpUnused */
    /**
     * Gets the LogEntry's timestamp represented in the specified timezone.
     *
     * @param string $timezone A PHP supported timezone in the typical format "Country/Region", defaults to "UTC".
     *
     * @return string Returns the local timestamp, formatted according to LogEntry::TIMESTAMP_FORMAT_DATETIME.
     * @throws Exception
     */
    public function getTimestampLocal(string $timezone = ""): string
    {
        $timezone = $timezone ?: Config::getTimezone() ?: "UTC";

        return (new DateTimeImmutable($this->timestamp))
            ->setTimezone(new DateTimeZone($timezone))
            ->format(self::TIMESTAMP_FORMAT_DATETIME);
    }

    /**
     * @var string
     */
    protected $channel;

    /**
     * @var int
     */
    protected $level;

    /**
     * @var string
     * @Accepts level_name
     */
    protected $levelName;

    /**
     * @var string
     */
    protected $message;

    /**
     * @var array|null
     */
    protected $context;

    /**
     * @var array|null
     */
    protected $extra;


    /** @noinspection PhpUnused */
    /**
     * @param array $row
     *
     * @return LogEntry|null
     */
    public static function fromRow(array $row): ?LogEntry
    {
        if(array_key_exists("id", $row))
            unset($row["id"]);

        /*
        if(array_key_exists("level_name", $row))
        {
            $row["levelName"] = $row["level_name"];
            unset($row["level_name"]);
        }
        */

        $row["message"] = str_replace(Log::MESSAGE_NEWLINE_PLACEHOLDER, "\n", $row["message"]);

        $context = json_decode($row["context"], true);
        if (json_last_error() == JSON_ERROR_NONE)
            $row["context"] = $context;

        $extra = json_decode($row["extra"], true);
        if (json_last_error() == JSON_ERROR_NONE)
            $row["extra"] = $extra;

        //var_dump($row);

        return new LogEntry($row);
    }



    /** @noinspection PhpUnused */
    /**
     * @param string $text
     * @return Collection|null
     * @throws Exception
     */
    public static function fromText(string $text): ?Collection
    {
        try
        {
            // Match the text against the RegEx pattern.
            preg_match_all(self::REGEX_ENTRY_HEADER, $text, $matches, PREG_OFFSET_CAPTURE);

            $entries = new Collection(LogEntry::class);

            $blocks = [];
            for($i = 0; $i < count($matches[0]); $i++)
            {
                $blocks[] = [
                    "index" => $matches[0][$i][1],
                    "chars" =>
                        ($i === count($matches[0]) - 1 ?
                            strlen($text) :
                            $matches[0][$i + 1][1]) - $matches[0][$i][1],
                    "entry" => substr(
                        $text,
                        $matches[0][$i][1],
                        ($i === count($matches[0]) - 1 ?
                            strlen($text) :
                            $matches[0][$i + 1][1]) - $matches[0][$i][1]
                    )
                ];

                $lines = array_filter(explode("\n", $blocks[$i]["entry"]));

                if(count($lines) !== 3)
                    continue;

                if(preg_match(self::REGEX_ENTRY_HEADER, $lines[0], $header))
                {
                    $data = [
                        "timestamp" => $header[1],
                        "channel" => $header[2],
                        "level" => constant("\Monolog\Logger::{$header[3]}"),
                        "levelName" => $header[3],
                        "message" => str_replace(Log::MESSAGE_NEWLINE_PLACEHOLDER, "\n", $header[4]),
                    ];

                    $context = json_decode($lines[1], TRUE);
                    $data["context"] = (json_last_error() == JSON_ERROR_NONE) ? $context : [];

                    $extra = json_decode($lines[2], TRUE);
                    $data["extra"] = (json_last_error() == JSON_ERROR_NONE) ? $extra : [];

                    $entries->push(new LogEntry($data));
                }
            }

            return $entries;
        }
        catch(Exception $e)
        {
            return null;
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $context =
            is_array($this->context) ?
                json_encode($this->context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) :
                $this->context;

        $extra =
            is_array($this->extra) ?
                json_encode($this->extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) :
                $this->extra;

        return
            "[{$this->timestamp}] " .
            "[" . ($this->channel ? "{$this->channel}." : "") . "{$this->levelName}] {$this->message}\n" .
            ($context ? "$context" : "") . "\n" .
            ($extra ? "$extra" : ""). "\n";
    }



}
