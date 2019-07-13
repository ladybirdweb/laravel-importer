<?php

namespace LadyBird\StreamImport\Http\Controllers;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use LadyBird\StreamImport\Factory;
use Illuminate\Support\Facades\Storage;
use LadyBird\StreamImport\Jobs\TestJob;
use LadyBird\StreamImport\Jobs\ImportJob;
use LadyBird\StreamImport\Jobs\ChunkImport;

class ImportController extends Controller
{
    //private $file_path="file.json";
    private $model;
    protected $saved_file = 'file.json';

    public function getDbCols($source)
    {
        $factory = new Factory();
        $this->model = $factory->make($source);

        if (is_null($this->model)) {
            return;
        }

        if (empty($this->model->getHidden())) {
            return array_unique($this->model->getFillable());
        } else {
            return array_unique(array_merge($this->model->getFillable(), $this->model->getHidden()));
        }
    }

    public function parseImport($source, Request $request)
    {
        $returnArray = [];

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('csv_file')->getRealPath();

        $data = array_map('str_getcsv', file($path));

        if ($request->has('header')) {
            array_shift($data);
        }

        array_shift($data);

        //dd($this);

        Storage::disk('local')->put($this->saved_file, json_encode($data));

        //$csv_data = $data[0];
        $db_cols = $this->getDbCols($source);

        if (is_null($db_cols)) {
            return response()->json(['message'=>'No Such Model Found in App'], 500);
        }

        $relations = $this->model->relationships();

        $relationship_array = [];

        foreach ($relations as $key => $value) {
            $model = new $value['model'];
            if (empty($model->getHidden())) {
                $relationship_array[] = implode(' ', array_values(array_unique(array_map(function ($v) use ($key) {
                    return $key.'.'.$v;
                }, $model->getFillable()))));
            } else {
                $relationship_array[] = implode(' ', array_values(array_unique(array_merge(array_map(function ($v) use ($key) {
                    return $key.'.'.$v;
                }, $model->getFillable()), array_map(function ($v) use ($key) {
                    return $key.'.'.$v;
                }, $model->getHidden())))));
            }
        }

        $returnArray['csv_sample_row'] = ($data[0]);
        $returnArray['database_columns'] = array_merge(array_unique($db_cols), $relationship_array);

        return response()->json($returnArray, 200);
    }

    public function processImport(Request $request, $source)
    {
        dd($request['fields']);

        if (count(array_diff_assoc($request['fields'], array_unique($request['fields']))) >= 1) {
            return response()->json(['message'=>'Duplicate Mappings are not allowed'], 500);
        }

        // // foreach($request['fields'] as $key => $value) {
        // //     if(empty($request['fields'][$key]) || isNull($request['fields'][$key])) {
        // //         unset($request['fields'][$key]);
        // //     }
        // // }

        // $factory = new Factory();
        // $this->model = $factory->make($source);

        // $relations = $this->model->relationships();

        // $db_cols = $this->getDbCols($source);

        // $tempArray = array();
        // $final_json_array = array();
        $temp_file_contents = Storage::disk('local')->get($this->saved_file);
        $temp_file_contents = json_decode($temp_file_contents, true);

        foreach (array_chunk($temp_file_contents, 5000) as $row) {
            ChunkImport::dispatch($row, $source, $request);
        }
        // foreach($request->fields as $key => $value) {

        //     if(empty($value) || is_null($value)) {
        //         continue;
        //     } else {
        //         $tempArray[$value] = $row[$key];
        //     }

        // } //foreach inner

        // ksort($tempArray);

        // foreach($tempArray as $key => $value) {
        //     foreach(array_keys($relations) as $rel) {

        //         if(strpos($key,$rel)!== false) {

        //            if($relations[$rel]['type'] == "BelongsTo") {

        //             $relationModel = new $relations[$rel]['model']();
        //             $record = $relationModel::where(str_replace($rel.".",'',$key),$value)->first();

        //             if($record === null) {
        //                 $record = $relationModel::create([
        //                     str_replace($rel.".",'',$key) => $value
        //                 ]);
        //             }

        //                 $replace = $record->id;

        //             if(is_null($replace) || empty($replace)) {
        //                 $tempArray = array();
        //             }

        //             else if(in_array($nk = strtolower($rel."_id"),$db_cols)) {
        //                 $tempArray[$nk] = $replace;
        //                 unset($tempArray[$key]);
        //                 $replace = '';
        //             }

        //            } //inner if belongs to many
        //         } //outer if
        //     }

        // }

        //     if(empty($headers))
        //         $headers = array_keys($tempArray);

        //     if(!empty($tempArray)) {
        //         array_push($final_json_array,$tempArray);
        //     }
        //     $tempArray = array();

        // } //foreach

        // $rules = ($this->model->rules) ? : [];

        // $csvCollection = collect($final_json_array);

        // // dd($csvCollection);

        // $input_rows = $csvCollection->chunk(3000);

        // TestJob::dispatch($this->model->getTable(),$rules,$input_rows);

        // foreach(array_chunk($final_json_array,100000) as $t) {

        //     for($i=0;$i<count($t);$i++) {

        //         $validator = Validator::make($t[$i],$rules);

        //         if($validator->fails()) {

        //             return response()->json($validator->messages()->getMessages(), 500);
        //         }

        //         else {
        //             $this->model::insert($t[$i]);

        //         }

        //     } //for

        // } //foreach

        //ImportJob::dispatch($request,$source);

        return response()->json(['message' => 'Successfully Inserted'], 200);
    }
}
