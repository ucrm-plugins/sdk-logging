<?php
/** @noinspection PhpUnused */
/** @noinspection PhpUnusedPrivateFieldInspection */
declare(strict_types=1);

namespace UCRM\Logging;

use JsonSerializable;
use Monolog\Handler\HandlerInterface;
use UCRM\Common\Plugin;
use UCRM\Logging\Monolog\Handlers\Sqlite3Handler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;
use \Exception;
use \ReflectionClass;
use \ReflectionException;

/**
 * Class Log
 *
 * @package MVQN\UCRM\Plugins
 * @author Ryan Spaeth <rspaeth@spaethtech.com>
 * @final
 */
final class Log
{
    // =================================================================================================================
    // CONSTANTS
    // -----------------------------------------------------------------------------------------------------------------

    /** @const int The options to be used when json_encode() is called. */
    private const DEFAULT_JSON_OPTIONS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    public const MESSAGE_NEWLINE_PLACEHOLDER = "##NEWLINE##";

    private const DEFAULT_TIMESTAMP_FORMAT = "Y-m-d H:i:s.uP";
    private const DEFAULT_ROW_ENTRY_FORMAT = "[%datetime%] [%level_name%] %message%\n%context%\n%extra%\n";
    private const CHANNEL_ROW_ENTRY_FORMAT = "[%datetime%] [%channel%.%level_name%] %message%\n%context%\n%extra%\n";

    public const UCRM = "UCRM";
    public const HTTP = "HTTP";
    public const REST = "REST";
    public const DATA = "DATA";

    // =================================================================================================================
    // FILE-BASED LOGGING
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Gets the absolute path to the Plugin's log file.
     *
     * @return string Returns the absolute path to the 'data/plugin.log' file and creates it if missing.
     * @throws Exceptions\PluginNotInitializedException
     */
    public static function pluginFile(): string
    {
        // Get the absolute path to the data folder, creating the folder as needed.
        $path = Plugin::getDataPath(). DIRECTORY_SEPARATOR . "plugin.log";

        // IF the current log file does not exist...
        if(!file_exists($path))
        {
            // THEN create it and append a notification.
            file_put_contents($path, "");
            self::info("Log file created!");
        }

        // Return the absolute path to the current log file.
        return realpath($path) ?: $path;
    }

    // =================================================================================================================
    // SQL-BASED LOGGING
    // -----------------------------------------------------------------------------------------------------------------

    private static $_loggers = [];

    private static function getLoggers(): array
    {
        if(self::$_loggers === [])
        {
            self::$_loggers = [
                self::UCRM => self::addUcrmFileLogger("UCRM"), // plugin.log
                self::HTTP => self::addDatabaseLogger("HTTP"),
                self::REST => self::addDatabaseLogger("REST"),
                self::DATA => self::addDatabaseLogger("DATA"),
            ];
        }

        return self::$_loggers;
    }

    public static function getLogger(string $name = self::UCRM): ?Logger
    {
        return array_key_exists($name, self::getLoggers()) ? self::$_loggers[$name] : null;
    }



    private static $_standardHandler = null;
    private static $_databaseHandler = null;
    private static $_ucrmFileHandler = null;

    private static function addDatabaseLogger(string $name = self::UCRM): Logger
    {
        // Instantiate a new logger.
        $logger = new Logger($name);

        // IF the current system is running the PHP Built-In Web Server (CLI)...
        if(PHP_SAPI === "cli-server")
        {
            // ...THEN setup the "stdout" StreamHandler.

            // NOTE: Here we configure a static instance of the StreamHandler, if one is not already instantiated!
            if(!self::$_standardHandler)
                self::$_standardHandler = (new StreamHandler("php://stdout"))
                    ->setFormatter(new LineFormatter(self::CHANNEL_ROW_ENTRY_FORMAT, self::DEFAULT_TIMESTAMP_FORMAT));

            // And add it as a secondary Handler.
            $logger->pushHandler(self::$_standardHandler);
        }

        // NOTE: Here we configure a static instance of the Sqlite3Handler, if one is not already instantiated!
        if(!self::$_databaseHandler)
            self::$_databaseHandler = new Sqlite3Handler(Plugin::getDataPath(). DIRECTORY_SEPARATOR . "plugin.db");

        // And add it as the primary Handler.
        $logger->pushHandler(self::$_databaseHandler);

        // NOTE: Add any additional Processors below, custom or otherwise...
        $logger->pushProcessor(new IntrospectionProcessor());
        $logger->pushProcessor(new WebProcessor());

        // Finally, return the newly added Logger!
        return $logger;
    }

    private static function addUcrmFileLogger(string $name = self::UCRM): Logger
    {
        // Instantiate a new logger.
        $logger = new Logger($name);

        // IF the current system is running the PHP Built-In Web Server (CLI)...
        if(PHP_SAPI === "cli-server")
        {
            // ...THEN setup the "stdout" StreamHandler.

            // NOTE: Here we configure a static instance of the StreamHandler, if one is not already instantiated!
            if(!self::$_standardHandler)
                self::$_standardHandler = (new StreamHandler("php://stdout"))
                    ->setFormatter(new LineFormatter(self::CHANNEL_ROW_ENTRY_FORMAT, self::DEFAULT_TIMESTAMP_FORMAT));

            // And add it as a secondary Handler.
            $logger->pushHandler(self::$_standardHandler);
        }

        // NOTE: Here we configure a static instance of the StreamHandler, if one is not already instantiated!
        if(!self::$_ucrmFileHandler)
            self::$_ucrmFileHandler = (new StreamHandler(Plugin::getDataPath(). DIRECTORY_SEPARATOR . "plugin.log"))
                ->setFormatter(new LineFormatter(self::CHANNEL_ROW_ENTRY_FORMAT, self::DEFAULT_TIMESTAMP_FORMAT));

        // And add it as the primary Handler.
        $logger->pushHandler(self::$_ucrmFileHandler);

        // NOTE: Add any additional Processors below, custom or otherwise...
        $logger->pushProcessor(new IntrospectionProcessor());
        $logger->pushProcessor(new WebProcessor());

        // Finally, return the newly added Logger!
        return $logger;
    }

    /** @noinspection PhpUnused */
    /**
     * @param Logger $logger
     *
     * @return Logger
     */
    public static function addLogger(Logger $logger): Logger
    {
        // NOTE: Here I assume that the end-user wants to override the identically named Logger.

        // IF the Logger's name already exists in the current loggers, THEN remove it!
        if(array_key_exists($logger->getName(), self::getLoggers()))
            unset(self::$_loggers[$logger->getName()]);

        // And then add the new Logger to the current loggers.
        self::$_loggers[$logger->getName()] = $logger;

        // Finally, return the newly added Logger!
        return $logger;
    }

    // =================================================================================================================
    // WRITING
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @param HandlerInterface $handler
     * @param string $property
     *
     * @return mixed
     * @throws ReflectionException
     */
    private static function getHandlerProperty(HandlerInterface $handler, string $property)
    {
        $reflection = new ReflectionClass($handler);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($handler);
    }

    /**
     * Clears the entries from all supported Loggers, matching the specified "channel".
     *
     * @param string $message An optional message to append to the cleared Logger.
     * @param string $name
     *
     * @return int
     * @throws ReflectionException
     */
    public static function clear(string $message = "", string $name = self::UCRM): int
    {
        if(!($logger = self::getLogger($name)))
            return -1;

        $cleared = 0;

        foreach($logger->getHandlers() as $handler)
        {
            if(is_a($handler, StreamHandler::class) &&
                ($url = self::getHandlerProperty($handler, "url")) && $url !== "php://stdout")
            {
                $path = realpath($url);
                $lines = 0;

                if($path && is_file($path))
                {
                    $lines = count(explode("\n", file_get_contents($path)));
                    file_put_contents($path, "", LOCK_EX);
                }

                if(file_get_contents($path) === "")
                {
                    $cleared++;

                    if($message)
                        Log::info($message, $name, [ "cleared" => $lines ]);
                }

                continue;
            }

            if(is_a($handler, Sqlite3Handler::class))
            {
                $pdo = self::getHandlerProperty($handler, "pdo");

                /** @noinspection SqlResolve  */
                $count = $pdo->exec("
                    DELETE FROM logs WHERE channel = '$name';                
                ");

                if($count > 0)
                {
                    $cleared++;

                    if($message)
                        Log::info($message, $name, [ "cleared" => $count ]);
                }

                continue;
            }

            // NOTE: Add any other types of clear() functionality for the other Handlers as needed!
            // ...
        }

        return $cleared;
    }

    public static function last(string $name = self::UCRM): ?LogEntry
    {
        if(!($logger = self::getLogger($name)))
            return null;

        foreach($logger->getHandlers() as $handler)
        {

            if(is_a($handler, StreamHandler::class) &&
                ($url = self::getHandlerProperty($handler, "url")) && $url !== "php://stdout")
            {
                $path = realpath($url);

                if($path && is_file($path))
                {
                    //$lines = explode("\n", file_get_contents($path));
                    //$last = $lines[count($lines) - 1];
                    /** @var LogEntry $entry */
                    $entry = LogEntry::fromText(file_get_contents($path))->last();

                    return $entry;
                }

                continue;
            }


            if(is_a($handler, Sqlite3Handler::class))
            {
                //$pdo = self::getHandlerProperty($handler, "pdo");

                /** @noinspection SqlResolve  */
                $results = Plugin::dbQuery("
                    SELECT * FROM logs WHERE channel = '$name' ORDER BY timestamp DESC LIMIT 1;                
                ");

                if($results && count($results) === 1)
                    return LogEntry::fromRow($results[0]);

                continue;
            }

            // NOTE: Add any other types of clear() functionality for the other Handlers as needed!
            // ...
        }

        return null;
    }




    /**
     * Writes a message to the current log file.
     *
     * @param string $message The message to be appended to the current log file.
     * @param string $severity An optional severity level to flag the log entry.
     * @return LogEntry Returns the logged entry.
     * @throws Exceptions\PluginNotInitializedException
     * @deprecated
     */
    /*
    public static function write(string $message, string $severity = LogEntry::SEVERITY_NONE): LogEntry
    {
        // Get the current log file's path.
        $logFile = self::logFile();

        //$message = str_replace("\n", "\n                             ", $message);
        $entry = new LogEntry(new \DateTimeImmutable(), $severity, $message);

        // Append the contents to the current log file, creating it as needed.
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);

        // Return the exact representation of the line!
        return $entry;
    }
    */
    /**
     * Writes a message to the current log file.
     *
     * @param array $array The array to write to the current log file.
     * @param string $severity An optional severity level to flag the log entry.
     * @param int $options An optional set of valid JSON_OPTIONS that should be used when encoding the array.
     * @return LogEntry Returns the logged entry.
     * @throws Exceptions\PluginNotInitializedException
     * @deprecated
     */
    /*
    public static function writeArray(array $array, string $severity = "",
        int $options = self::DEFAULT_JSON_OPTIONS): LogEntry
    {
        // JSON encode the array and then write it to the current log file.
        $text = json_encode($array, $options);
        return self::write($text, $severity);
    }
    */

    /**
     * Writes a message to the current log file.
     *
     * @param JsonSerializable $object The object (that implements JsonSerializable) to write to the current log file.
     * @param string $severity An optional severity level to flag the log entry.
     * @param int $options An optional set of valid JSON_OPTIONS that should be used when encoding the array.
     * @return LogEntry Returns the logged entry.
     * @throws Exceptions\PluginNotInitializedException
     * @deprecated
     */
    /*
    public static function writeObject(\JsonSerializable $object, string $severity = "",
        int $options = self::DEFAULT_JSON_OPTIONS): LogEntry
    {
        // JSON encode the object and then write it to the current log file.
        $text = json_encode($object, $options);
        return self::write($text, $severity);
    }
    */

    /**
     * Writes a message to the current log file, automatically marking it as DEBUG.
     *
     * @param string $message The message to be appended to the current log file.
     * @param string $log
     * @param array $context
     *
     * @return LogEntry Returns the logged entry.
     */
    public static function debug(string $message, string $log = self::UCRM, array $context = []): ?LogEntry
    {
        if(!($logger = self::getLogger($log)))
            return null;

        $message = str_replace("\n", self::MESSAGE_NEWLINE_PLACEHOLDER, $message);

        if(!($result = $logger->debug($message, $context)))
            return null;

        // TODO: Finish deprecating the old Log::debug().
        return null;
    }

    /**
     * Writes a message to the current log file, automatically marking it as INFO.
     *
     * @param string $message The message to be appended to the current log file.
     * @param string $log
     * @param array $context
     *
     * @return LogEntry Returns the logged entry.
     */
    public static function info(string $message, string $log = self::UCRM, array $context = []): ?LogEntry
    {
        if(!($logger = self::getLogger($log)))
            return null;

        $message = str_replace("\n", self::MESSAGE_NEWLINE_PLACEHOLDER, $message);

        if(!($result = $logger->info($message, $context)))
            return null;

        return self::last($log);
    }

    /**
     * Writes a message to the current log file, automatically marking it as WARNING.
     *
     * @param string $message The message to be appended to the current log file.
     * @param string $log
     *
     * @return LogEntry Returns the logged entry.
     */
    public static function warning(string $message, string $log = self::UCRM): ?LogEntry
    {
        if(!($logger = self::getLogger($log)))
            return null;

        $message = str_replace("\n", self::MESSAGE_NEWLINE_PLACEHOLDER, $message);

        if(!($result = $logger->warning($message)))
            return null;

        // TODO: Finish deprecating the old Log::warning().
        return null;
    }

    /**
     * Writes a message to the current log file, automatically marking it as ERROR.
     *
     * @param string $message The message to be appended to the current log file.
     * @param string $log
     * @param string $exception An optional Exception that should be thrown when this error is logged, defaults to NONE.
     *
     * @return LogEntry Returns the logged entry, when an Exception is not provided.
     */
    public static function error(string $message, string $log = self::UCRM, string $exception = ""): ?LogEntry
    {
        if(!($logger = self::getLogger($log)))
            return null;

        $message = str_replace("\n", self::MESSAGE_NEWLINE_PLACEHOLDER, $message);

        if(!($result = $logger->error($message)))
            return null;

        // TODO: Finish deprecating the old Log::error().
        if($exception !== "" && is_subclass_of($exception, Exception::class, true))
            throw new $exception($message);
        else
            return null;
    }


    // TODO: Fix this up to make it more useful!

    public static function http(string $message, string $log = self::UCRM, int $statusCode = 500, bool $die = true):
    ?LogEntry
    {
        if(!($logger = self::getLogger($log)))
            return null;

        $message = str_replace("\n", self::MESSAGE_NEWLINE_PLACEHOLDER, $message);

        if(!($result = $logger->alert($message)))
            return null;

        http_response_code($statusCode);

        // TODO: Finish deprecating the old Log::http().
        if($die)
            die($message);
        else
            return null;
    }



}
