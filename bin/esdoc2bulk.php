#!/usr/bin/env php
<?php
/**
 * Created by PhpStorm.
 * User: iwai
 * Date: 2017/02/03
 * Time: 13:05
 */

ini_set('date.timezone', 'Asia/Tokyo');

if (PHP_SAPI !== 'cli') {
    echo sprintf('Warning: %s should be invoked via the CLI version of PHP, not the %s SAPI'.PHP_EOL, $argv[0], PHP_SAPI);
    exit(1);
}

require_once __DIR__.'/../vendor/autoload.php';

use CHH\Optparse;

$parser = new Optparse\Parser();

function usage() {
    global $parser;
    fwrite(STDERR, "{$parser->usage()}\n");
    exit(1);
}

$parser->setExamples([
    sprintf("%s --index foo --type bar --routing userId --action update", $argv[0]),
    sprintf("%s --action update", $argv[0]),
]);

$action  = null;
$upsert  = null;
$doc_as_upsert  = false;
$partial_doc    = null;
$index   = null;
$type    = null;
$routing = null;
$script  = null;

$parser->addFlag('help', [ 'alias' => '-h' ], 'usage');
$parser->addFlag('verbose', [ 'alias' => '-v' ]);

$parser->addFlagVar('action', $action, [ 'has_value' => true, 'required' => true ]);
$parser->addFlagVar('upsert', $upsert, [ 'has_value' => true ]);
$parser->addFlag('doc_as_upsert', [ 'default' => false ]);
$parser->addFlagVar('partial_doc', $partial_doc, [ 'has_value' => true ]);

$parser->addFlagVar('index', $index, [ 'has_value' => true ]);
$parser->addFlagVar('type', $type, [ 'has_value' => true ]);
$parser->addFlagVar('routing', $routing, [ 'has_value' => true ]);
$parser->addFlagVar('script', $script, [ 'has_value' => true ]);

$parser->addArgument('file', [ 'required' => false ]);

try {
    $parser->parse();
} catch (\Exception $e) {
    usage();
}

$file_path = $parser['file'];

try {

    if ($file_path) {
        if (($fp = fopen($file_path, 'r')) === false) {
            die('Could not open '.$file_path);
        }
    } else {
        if (($fp = fopen('php://stdin', 'r')) === false) {
            usage();
        }
        $read = [$fp];
        $w = $e = null;
        $num_changed_streams = stream_select($read, $w, $e, 1);

        if (!$num_changed_streams) {
            usage();
        }
    }

    $line = 1;

    while (!feof($fp)) {
        $json = trim(fgets($fp));

        if (empty($json)) {
            continue;
        }

        $doc = json_decode($json, JSON_OBJECT_AS_ARRAY);

        if (!$doc) {
            fwrite(STDERR, sprintf('ERROR: Decode failed: %s', $json) . PHP_EOL);
        }

        $doc2bulk = new \Esdoc2Bulk($doc);

        if ($action === Esdoc2Bulk::INDEX) {

            $meta   = $doc2bulk->getMetaData($action, $index, $type, $routing);
            $source = $doc2bulk->getSource();

            echo json_encode($meta), "\n";
            echo json_encode($source, JSON_UNESCAPED_UNICODE), "\n";

        } elseif ($action === Esdoc2Bulk::CREATE) {

            $meta   = $doc2bulk->getMetaData($action, $index, $type, $routing);
            $source = $doc2bulk->getSource();

            echo json_encode($meta), "\n";
            echo json_encode($source, JSON_UNESCAPED_UNICODE), "\n";

        } elseif ($action === Esdoc2Bulk::DELETE) {

            $meta   = $doc2bulk->getMetaData($action, $index, $type, $routing);

            echo json_encode($meta), "\n";

        } elseif ($action === Esdoc2Bulk::UPDATE) {

            $meta   = $doc2bulk->getMetaData($action, $index, $type, $routing);

            if ($script) {
                $source = $doc2bulk->getScript($script);
            } else {
                $doc_as_upsert = $parser['doc_as_upsert'];

                $source = $doc2bulk->getDoc($doc_as_upsert, $partial_doc);
            }

            echo json_encode($meta), "\n";
            echo json_encode($source, JSON_UNESCAPED_UNICODE), "\n";

        } else {
            die(sprintf('Unsupported action: %s', $action));
        }

        foreach ($doc2bulk->getErrors() as $msg) {
            fwrite(STDERR, sprintf('%d: %s%s', $line, $msg, PHP_EOL));
        }
        foreach ($doc2bulk->getWarnings() as $msg) {
            fwrite(STDERR, sprintf('%d: %s%s', $line, $msg, PHP_EOL));
        }

        $line = $line + 1;
    }
    fclose($fp);

} catch (\Exception $e) {
    throw $e;
}
