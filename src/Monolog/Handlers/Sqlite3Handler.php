<?php
declare(strict_types=1);
namespace UCRM\Logging\Monolog\Handlers;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use PDO;
use UCRM\Logging\LogEntry;

/**
 * This class is a handler for Monolog, which can be used to write records in a Sqlite3 database.
 *
 * Class Sqlite3Handler
 * @package mvqn\ucrm-plugin-sdk
 */
class Sqlite3Handler extends AbstractProcessingHandler
{
    /**
     * @var bool Used to determine whether or not this handler has been initialized.
     */
    private $initialized = false;

    /**
     * @var PDO The PHP Database Object for handling the database interactions.
     */
    protected $pdo;

    /**
     * @var string The database table name in which to store the logs.
     */
    private $table;

    /**
     * Constructor of this class, sets the PDO and calls parent constructor
     *
     * @param PDO $pdo PDO Connector for the database
     * @param int $level
     * @param bool $bubble
     */
    public function __construct(/*PDO $pdo*/ string $path, int $level = Logger::DEBUG, bool $bubble = true)
    {
        if (!$this->pdo)
        {
            try
            {
                $this->pdo = new \PDO("sqlite:$path");
                //echo "CONNECTED\n";
            } catch(\PDOException $e)
            {
                die($e);
            }

        }


        $this->table = "logs";

        parent::__construct($level, $bubble);
    }

    /**
     * Initializes this handler by creating the table if it not exists
     */
    private function initialize()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS `{$this->table}`
            (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                timestamp   TEXT,
                channel     TEXT    NOT NULL,
                level       INTEGER NOT NULL,
                level_name  TEXT    NOT NULL,
                message     TEXT,
                context     TEXT,
                extra       TEXT                
            );
        ");

        $this->initialized = true;
    }



    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  $record[]
     * @return void
     */
    protected function write(array $record): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $columns = [
            "timestamp",
            "channel",
            "level",
            "level_name",
            "message",
            "context",
            "extra"
        ];

        $fields = array_map(
            function(string $column) {
                return ":$column";
            },
            $columns
        );

        $columns = implode(",", $columns);
        $fields = implode(",", $fields);

        $statement = $this->pdo->prepare("
            INSERT INTO `{$this->table}` ($columns) VALUES ($fields);
        ");

        $statement->execute([
            "timestamp" => $record["datetime"]->format(LogEntry::TIMESTAMP_FORMAT_DATETIME),
            "channel" => $record["channel"],
            "level" => $record["level"],
            "level_name" => $record["level_name"],
            "message" => $record["message"],
            "context" => json_encode($record["context"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "extra" => json_encode($record["extra"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);

    }
}
