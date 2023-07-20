#!/usr/bin/php
<?php

require_once(dirname(__DIR__) . '/lib/class-rest.php');

/**
* Mimic WP essential functions mimic if in a standalone test environment.
*/
if (!function_exists('is_rest_error')) {
  function is_rest_error($thing)
  {
    if ($thing instanceof Error) return true;
  }
}

function get_config($defaultIniFile, $additionalIni = '')
{
  static $config = null;

  if ($config !== null) {
    return $config;
  }

  // Read the ini file contents
  $iniContents = '';
  if (file_exists($defaultIniFile) && is_readable($defaultIniFile)) {
    $iniContents .= file_get_contents($defaultIniFile);
  }
  if ($additionalIni && file_exists($additionalIni) && is_readable($additionalIni)) {
    $iniContents .= "\n" . file_get_contents($additionalIni);
  }

  // Parse the ini contents manually
  $config = parse_ini_string($iniContents, true, INI_SCANNER_RAW);

  if ($config === false) {
    return new Error('Error parsing ini file ' . $defaultIniFile);
  }

  // Process constants recursively
  $config = process_constants($config);
  // error_log("config " . print_r($config, true));

  return $config;
}

function process_constants( $config, $section = false) {
  if($section === false) $section = $config;
  foreach ($section as $key => $value) {
    if (is_array($value)) {
      $section[$key] = process_constants( $config, $value );
    } else {
      $section[$key] = replace_constants( $config, $value);
    }
  }
  return $section;
}

function replace_constants($config, $value)
{
  $found = preg_match_all('/\${([^\|]+)\|([^\}]+)}/', $value, $matches);
  if(!$found) return $value;
  foreach ($matches[0] as $index => $match) {
    $sectionName = $matches[1][$index];
    $option = $matches[2][$index];

    if (isset($config[$sectionName][$option])) {
      $replacement = $config[$sectionName][$option];
      $value = str_replace($match, $replacement, $value);
    }
  }
  return $value;
}

function array_get_case_insensitive($array, $key)
{
  $key = strtolower($key);
  $lowercaseKeys = array_map('strtolower', array_keys($array));
  $lowercaseKey = strtolower($key);

  $index = array_search($lowercaseKey, $lowercaseKeys, true);

  if ($index !== false) {
    $keys = array_keys($array);
    return $array[$keys[$index]];
  }

  return null;
}

if (!function_exists('get_option')) {
  function get_option($option, $default = null)
  {
    $scriptFilename = __FILE__;
    $scriptBasename = pathinfo($scriptFilename, PATHINFO_FILENAME);
    $defaultIniFile = $_SERVER['HOME'] . '/.' . $scriptBasename . '.ini';

    $additionalIni = isset($GLOBALS['additional_ini']) ? $GLOBALS['additional_ini'] : '';

    $config = get_config($defaultIniFile, $additionalIni);

    switch ($option) {
      case 'opensim_rest_config':
      $baseURL = $config['Const']['BaseURL'] ?? 'localhost';
      $consolePort = $config['Network']['ConsolePort'] ?? 8002;
      $consoleUser = $config['Network']['ConsoleUser'] ?? '';
      $consolePass = $config['Network']['ConsolePass'] ?? '';
      return array(
      'uri' => "$baseURL:$consolePort",
      'ConsoleUser' => $consoleUser,
      'ConsolePass' => $consolePass,
      );

      default:
      $parts = explode(':', $option);
      $section = $parts[0];
      $key = isset($parts[1]) ? $parts[1] : null;

      if (isset($config[$section])) {
        $sectionData = $config[$section];
        $sectionData = array_get_case_insensitive($config, $section);

        if ($key !== null) {
          return array_get_case_insensitive($sectionData, $key) ?? $default;
          // return $sectionData[$key] ?? $default;
        } else {
          return $sectionData;
        }
      }
    }

    return $default;
  }
}

$firstArgument = isset($argv[1]) ? $argv[1] : '';
$additionalIni = null;
if (file_exists($firstArgument) && is_file($firstArgument) && is_readable($firstArgument)) {
  $additionalIni = $firstArgument;
  $command = implode(' ', array_slice($argv, 2));
} else {
  $command = implode(' ', array_slice($argv, 1));
}

if (empty($command)) {
  error_log("Usage: php opensim-rest-cli.php [<ini_file>] <command>");
  exit;
}

$GLOBALS['additional_ini'] = $additionalIni;

$rest_args = get_option('opensim_rest_config');

if (is_rest_error($rest_args)) {
  die("Error reading config: " . $rest_args->getMessage() . "\n");
}

$session = opensim_rest_session($rest_args);
if (is_rest_error($session)) {
  echo "OpenSim_Rest error: " . $session->getMessage() . "\n";
} else if ((!$session)) {
  echo "OpenSim_Rest new session unknown error\n";
} else {
  // Send the command and retrieve the lines of the response
  $responseLines = $session->sendCommand($command);
  if (is_rest_error($responseLines)) {
    echo "OpenSim_Rest->sendCommand error: " . $responseLines->getMessage() . "\n";
  } else {
    echo trim(join("\n", $responseLines)) . "\n";
  }
}
