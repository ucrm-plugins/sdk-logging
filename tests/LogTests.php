<?php
/** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use UCRM\Common\Exceptions;
use UCRM\Common\Plugin;
use UCRM\Logging\Log;


define("EXAMPLE_PLUGIN_ROOT", implode(DIRECTORY_SEPARATOR, [ __DIR__ , "..", "examples", "plugin-example", "" ]));

class LogTests extends TestCase
{

    /**
     * @throws Exceptions\RequiredDirectoryNotFoundException
     * @throws Exceptions\RequiredFileNotFoundException
     */
    protected function setUp(): void
    {
        Plugin::initialize(EXAMPLE_PLUGIN_ROOT);
    }

    /**
     * @covers Log::pluginFile
     *
     * @throws Exceptions\PluginNotInitializedException
     */
    public function testGetLogPluginFilePathWhenItDoesNotExist()
    {
        // Build the "expected" absolute path string.
        $expected = realpath(EXAMPLE_PLUGIN_ROOT . implode(DIRECTORY_SEPARATOR, [ "data", "plugin.log" ]));

        // IF the file exists, THEN remove it!
        if($real = realpath($expected))
            unlink($real);

        // Assert that the file is missing!
        $this->assertFileNotExists($real);

        // Now call the pluginFile() method...
        $actual = Log::pluginFile();

        // And assert that the file was created and the returned path is the same as the expected!
        $this->assertFileExists($actual);
        $this->assertEquals($expected, $actual);
    }

    /**
     * @covers Log::pluginFile
     * @depends testGetLogPluginFilePathWhenItDoesNotExist
     *
     * @throws Exceptions\PluginNotInitializedException
     */
    public function testGetLogPluginFilePathWhenItDoesExist()
    {
        // Build the "expected" absolute path string.
        $expected = realpath(EXAMPLE_PLUGIN_ROOT . implode(DIRECTORY_SEPARATOR, [ "data", "plugin.log" ]));

        // Assert that the file exists!
        $this->assertFileExists(realpath($expected));

        // Now call the pluginFile() method...
        $actual = Log::pluginFile();

        // And assert that the returned path is the same as the expected!
        $this->assertEquals($expected, $actual);


    }



    public function testGetLoggers()
    {
        var_dump(Log::getLogger(Log::UCRM));
    }



}
