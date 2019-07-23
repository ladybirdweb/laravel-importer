<?php

namespace LWS\Import\Http\Controllers;

use Closure;


use LWS\Import\Factory;
use LWS\Import\CsvIterator;
use Illuminate\Http\Request;
use SuperClosure\Serializer;
use LWS\Import\Jobs\ClosureJob;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use LWS\Import\Jobs\ImportJob;

class ImportController extends Controller
{
    private $model;
    protected $saved_file='file.json';
    protected $callableClosure;

    public function getModelObject($source)
    {
        $factory = new Factory();
        $model = $factory->make($source);

        if(!is_null($model))
            return $model;
    }

    public function getDbCols($source)
    {
        
        
        $factory = new Factory();
        $this->model = $factory->make($source);

        
        if(is_null($this->model)){
        
            return;
        }

        if(empty($this->model->getHidden())) {
            return array_unique($this->model->getFillable());
        } else {
            return(array_unique(array_merge($this->model->getFillable(),$this->model->getHidden())));
        }
    }

    

    public function parseImport($source,Request $request)
    {
       
        
        
        

        $returnArray = array();

        $request->validate([
            'csv_file' => "required|file|mimes:csv,txt"
        ]);
        
        $path = $request->file('csv_file')->getRealPath();
        $pathSave = $request->csv_file->storeAs('csv', 'file.csv','local');
 
        
        $data = array_map('str_getcsv',file($path));
        
        if($request->has('header')) {
            array_shift($data);
        }

        array_shift($data);
        
        
        Storage::disk('local')->put($this->saved_file,json_encode($data));
        // dd(Storage::disk('local')->exists('csv/file.csv'));

            

        //$csv_data = $data[0];
        $db_cols = $this->getDbCols($source);
        
        if(is_null($db_cols)) {
            
            return response()->json(["message"=>"No Such Model Found in App"], 500);
        }

        $relations = $this->model->relationships();

        

        $relationship_array = array();

        foreach($relations as $key => $value) {
            $model = new $value['model'];
            
            if(!empty($model->getFillable())) {

                if(empty($model->getHidden())) {

                    $relationship_array[] = implode(" ",array_values(array_unique(array_map(function ($v) use ($key) { return $key.".".$v;},$model->getFillable()))));
    
                } else {
                    $relationship_array[] = implode(" ",array_values(array_unique(array_merge(array_map(function ($v) use ($key) { return $key.".".$v;},$model->getFillable()),array_map(function ($v) use ($key) { return $key.".".$v;},$model->getHidden())))));
                }

            } //not a fillable model
        }
        
        

        $returnArray['csv_sample_row'] = ($data[0]);
        $returnArray['database_columns'] = (!empty($relationship_array)) ? array_merge(array_unique($db_cols),$relationship_array) : array_unique($db_cols) ;

        
        //dd($returnArray);

        //  return response()->json($returnArray);


        return $returnArray;
        
       
       
        
    }

    

    public function process(Closure $callback)
    {
        // $wrapper = new SerializableClosure($callback);
        // $serialized = serialize($wrapper);

        
        //dd($this->callableClosure);
        
        $temp_file_contents = Storage::disk('local')->get($this->saved_file);
        $temp_file_contents = json_decode($temp_file_contents,true);

       

        foreach(array_chunk($temp_file_contents,1000) as $row) {
        //     // dispatch($callback($row))->delay(now()->addMinutes(10));
            // call_user_func($callback,$row);
            call_user_func($callback,$row);

            

            // $serialized = $serializer->serialize($callback);
            // dispatch(function() use ($serialized,$row){
            //     call_user_func($serialized,$row);
            // });
            
            // $pool = Pool::create();

            // foreach (array_chunk($temp_file_contents,10000) as $row) {
            //     $pool->add($callback($row))->then(function ($output) {
            //         // Handle success
            //     })->catch(function (Throwable $exception) {
            //         // Handle exception
            //     });
            // }

            // $pool->wait();
        }

        
        


        // $csv = new SplFileObject(storage_path()."/app/csv/file.csv");
        // $csv->setFlags(SplFileObject::READ_CSV);
        // $start = 0;
        // $batch = 500;
        // while (!$csv->eof()) {
        // foreach(new LimitIterator($csv, $start, $batch) as $line){
            
        // }
        // $start += $batch;
        // }    


    }

    
}