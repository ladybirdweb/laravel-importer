<?php

namespace LWS\Import;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class Import extends Controller
{
    private $model;
    protected $saved_file = 'file.json';
    protected $serialized;

    public function getModelObject($source)
    {
        $factory = new Factory();
        $model = $factory->make($source);

        if (! is_null($model)) {
            return $model;
        }
    }

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
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('csv_file')->getRealPath();

        $data = array_map('str_getcsv', file($path));

        if ($request->has('header')) {
            array_shift($data);
        }

        array_shift($data);

        Storage::disk('local')->put($this->saved_file, json_encode($data));

        $db_cols = $this->getDbCols($source);

        if (is_null($db_cols)) {
            return response()->json(['message'=>'No Such Model Found in App'], 500);
        }

        $relations = $this->model->relationships();

        $relationship_array = [];

        foreach ($relations as $key => $value) {
            $model = new $value['model'];

            if (! empty($model->getFillable())) {
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
            } //not a fillable model
        }

        $returnArray['csv_sample_row'] = ($data[0]);
        $returnArray['database_columns'] = (! empty($relationship_array)) ? array_merge(array_unique($db_cols), $relationship_array) : array_unique($db_cols);

        return response()->json($returnArray);
    }

    public function processImport(Closure $callback)
    {
        $temp_file_contents = Storage::disk('local')->get($this->saved_file);
        $temp_file_contents = json_decode($temp_file_contents, true);

        foreach (array_chunk($temp_file_contents, 1000) as $row) {
            dispatch(function () use ($callback,$row) {
                call_user_func($callback, $row);
            });
        }

        return response()->json(['message' => 'Importing..'], 200);
    }
}
