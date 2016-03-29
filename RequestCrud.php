<?php

namespace App\Libs;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of RequestCRUD
 *
 * @author Tuhin
 */
class RequestCrud extends LaraCrud {

    public function __construct($table) {
        if (!empty($table)) {
            $this->tables[] = $table;
        } else {
            $this->getTableList();
        }
        $this->loadDetails();
        $this->prepareRelation();
    }

    /**
     * 
     * @param type $tableName
     * @return type
     */
    private function generateContent($tableName) {
        $requestContent = file_get_contents(__DIR__ . '/templates/request.txt');
        $requestContent = str_replace("@@requestClassName@@", $this->getModelName($tableName) . "Request", $requestContent);

        $rulesText = '';

        if (isset($this->rules[$tableName]) && !empty($this->rules[$tableName])) {

            foreach ($this->rules[$tableName] as $colName => $rules) {
                if (strlen($colName) <= 1) {
                    continue;
                }
                $rulesText.="'$colName'=>'$rules'," . "\n";
            }
        }
        $requestContent = str_replace(" @@rules@@", $rulesText, $requestContent);

        return $requestContent;
    }

    /**
     * Generate Rules for rules method of Request table
     */
    public function allRules() {

        foreach ($this->tables as $tname) {
            if (in_array($tname, $this->pivotTables)) {
                continue;
            }
            $this->rules($tname);
        }
    }

    public function rules($tname) {
        $reservedColumns = ['id', 'created_at', 'updated_at'];

        foreach ($this->tableColumns[$tname] as $column) {
            $validationRules = '';

            if (in_array($column->Field, $reservedColumns)) {
                continue;
            }
            $type = $column->Type;
            //If it contains '( )' symbol then it must hold length or string seperated by comma for enum data type
            if (strpos($type, "(")) {
                $dataType = substr($type, 0, strpos($type, "("));
                $retVals = $this->extractRulesFromType($type);

                if ($dataType == 'enum') {
                    $validationRules .= 'in:' . $retVals . '|';
                } elseif ($dataType == 'varchar') {
                    $validationRules .="max:" . $retVals . '|';
                }
            }
            if (isset($this->foreignKeys[$tname])) {
                if (in_array($column->Field, $this->foreignKeys[$tname]['keys']) && isset($this->foreignKeys[$tname]['rel'][$column->Field])) {
                    $tableName = $this->foreignKeys[$tname]['rel'][$column->Field]->REFERENCED_TABLE_NAME;
                    $tableColumn = $this->foreignKeys[$tname]['rel'][$column->Field]->REFERENCED_COLUMN_NAME;
                    $validationRules.='exists:' . $tableName . ',' . $tableColumn;
                }
            } else {

                if ($column->Null == 'NO' && $column->Default == "") {
                    $validationRules.='required|';
                }
                if ($column->Key == 'UNI') {
                    $validationRules.='unique:' . $tname . ',' . $column->Field;
                }
            }
            if (!empty($validationRules)) {
                $this->rules[$tname][$column->Field] = rtrim($validationRules, "|");
            }
        }
    }

    public function create($table) {
        try {
            $signularTable = $this->getModelName($table) . 'Request';
            $fullPath = base_path('app/Http/Requests') . '/' . $signularTable . '.php';

            if (!file_exists($fullPath)) {
                $requestContent = $this->generateContent($table);
                file_put_contents($fullPath, $requestContent);
                return true;
            }
            return false;
        } catch (\Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    public function make() {
        try {
            $this->allRules();
            foreach ($this->tables as $table) {
                if (in_array($table, $this->pivotTables)) {
                    continue;
                }

                $this->create($table);
            }
        } catch (\Exception $ex) {
            $this->errors[] = $ex->getMessage();
        }
    }

}