<?php
/**
 * Created by PhpStorm.
 * User: iwai
 * Date: 2017/09/27
 * Time: 19:36
 */

class Esdoc2Bulk
{
    const INDEX  = 'index';
    const CREATE = 'create';
    const DELETE = 'delete';
    const UPDATE = 'update';

    protected $doc;
    protected $errors = [];
    protected $warnings = [];

    /**
     * Esdoc constructor.
     * @param array $doc
     *
     * {"_index":"","_type":"","_id":"","_score":0,"_source":{ // source fields // },"fields":{"_parent":""}}
     */
    function __construct(array $doc)
    {
        $this->doc = $doc;
    }

    public function getErrors()
    {
        return $this->errors;
    }
    public function getWarnings()
    {
        return $this->warnings;
    }

    protected function appendError($message)
    {
        $this->errors[] = $message;
    }
    protected function appendWarnings($message)
    {
        $this->warnings[] = $message;
    }

    public function getMetaData($action, $index = null, $type = null, $routingField = null)
    {
        $index = $index ?: $this->doc['_index'];
        $type  = $type  ?: $this->doc['_type'];
        $id    = $this->doc['_id'];

        $meta = [
            $action => [
                '_index' => $index,
                '_type'  => $type,
                '_id'    => $id,
            ]
        ];

        if (isset($this->doc['fields']) && isset($this->doc['fields']['_parent'])) {
            if ($this->doc['fields']['_parent'] !== '') {
                $meta[$action]['_parent'] = $this->doc['fields']['_parent'];
            } else {
                $this->appendWarnings('Empty _parent');
            }
        }

        if ($routingField !== null) {
            if (isset($this->doc['_source'][$routingField]) && $this->doc['_source'][$routingField] !== '') {
                $meta[$action]['_routing'] = $this->doc['_source'][$routingField];
            } else {
                $this->appendWarnings(sprintf('Undefined routing: %s', $routingField));
            }
        }

        return $meta;
    }

    public function getSource()
    {
        return $this->doc['_source'];
    }

    public function getDoc($docAsUpsert = false, $partial_doc = null)
    {
        if ($partial_doc) {
            $source = [
                'doc' => json_decode($partial_doc, JSON_OBJECT_AS_ARRAY)
            ];
        } else {
            $source = [
                'doc' => $this->doc['_source']
            ];
        }
        if ($docAsUpsert) {
            $source['doc_as_upsert'] = true;
        }

        return $source;
    }

    public function getScript($script = null)
    {
        $source = [
            'script' => $script,
            'params' => $this->doc['_source'],
            'upsert' => $this->doc['_source'],
        ];
        return $source;
    }

}