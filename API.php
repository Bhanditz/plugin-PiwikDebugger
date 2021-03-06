<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\PiwikDebugger;

use Piwik\Common;
use Piwik\Config;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Db;
use Piwik\Plugins\PiwikDebugger\Process;

/**
 * API for plugin PiwikDebugger
 *
 * @method static \Piwik\Plugins\PiwikDebugger\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    public function execQuery($query)
    {
        $start  = microtime(true);
        $query  = Common::unsanitizeInputValue($query);
        $result = Db::query($query)->fetchAll();
        $durationInMs = (microtime(true) - $start) * 1000;

        $columns = array();
        if (!empty($result)) {
            $columns = array_keys($result[0]);
        }

        return array(
            'hasResult' => !empty($result),
            'query'     => $query,
            'resultSet' => $result,
            'duration'  => number_format($durationInMs, 4) . 'ms',
            'columns'   => $columns
        );
    }

    public function getTablePrefix()
    {
        return Config::getInstance()->database['tables_prefix'];
    }

    public function getConfig()
    {
        $config = Config::getInstance();

        return array(
            'localPath'   => $config->getLocalPath(),
            'globalPath'  => $config->getGlobalPath(),
            'commonPath'  => $config->getCommonPath(),
            'configHost'  => $config->getConfigHostnameIfSet(),
            'hostname'    => Config::getHostname(),
            'writable'    => $config->isFileWritable(),
            'existsLocal' => $config->existsLocalConfig(),
            'localConfig' => _parse_ini_file($config->getLocalPath(), true),
            'commonConfig' => _parse_ini_file($config->getCommonPath(), true),
        );
    }

    public function enableTrackerDebug($enable)
    {
        $tracker = Config::getInstance()->Tracker;
        $tracker['debug'] = !empty($enable) ? 1 : 0;
        Config::getInstance()->Tracker = $tracker;
        Config::getInstance()->forceSave();
    }

    public function startCommandExecution($commandText, $commandId)
    {
        $process = Process::factory($commandId, $commandText);
        $process->execute();
    }

    public function pollCommandStatus($commandId, $outputStart)
    {
        $process = Process::factory($commandId);

        $result = array(
            'output' => $process->getOutput($outputStart),
            'returnCode' => $process->getReturnCode()
        );

        if ($result['returnCode'] !== null) {
            $process->finalizeExecution();
        }

        return $result;
    }
}