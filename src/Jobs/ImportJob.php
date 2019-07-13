<?php

namespace LadyBird\StreamImport\Jobs;

use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use LadyBird\StreamImport\Factory;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use LadyBird\StreamImport\Jobs\TestJob;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use JsonMachine\JsonMachine;

class ImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $source;
    protected $request;
    protected $model;


    public $timeout = 360;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Request $request,$source)
    {
        $this->request = $request->all();
        $this->source = $source;

        
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //dd($request['fields']);
        

        // if(count(array_diff_assoc($this->request['fields'], array_unique($this->request['fields'])))>=1) {
        //     return response()->json(["message"=>"Duplicate Mappings are not allowed"],500);
        // }
        
        // foreach($request['fields'] as $key => $value) {
        //     if(empty($request['fields'][$key]) || isNull($request['fields'][$key])) {
        //         unset($request['fields'][$key]);
        //     }
        // }
        

        $factory = new Factory();
        $this->model = $factory->make($this->source);

        

        $relations = $this->model->relationships();

        if(empty($this->model->getHidden())) {
            $db_cols =  array_unique($this->model->getFillable());
        } else {
            $db_cols = (array_unique(array_merge($this->model->getFillable(),$this->model->getHidden())));
        }

        $tempArray = array();
        $final_json_array = array();
        

        $temp_file_contents = JsonMachine::fromFile(storage_path()."/app/file.json");

        

        

        foreach($temp_file_contents as $row) {

            

            foreach($this->request['fields'] as $key => $value) {

                if(empty($value) || is_null($value)) {
                    continue;
                } else {
                    $tempArray[$value] = $row[$key];
                }

                
                

            } //foreach inner

            ksort($tempArray);

            

            foreach($tempArray as $key => $value) {
                foreach(array_keys($relations) as $rel) {
                    
                    if(strpos($key,$rel)!== false) {
                        
                       if($relations[$rel]['type'] == "BelongsTo") {

                        $relationModel = new $relations[$rel]['model']();
                        $record = $relationModel::where(str_replace($rel.".",'',$key),$value)->first();
                        
                        if($record === null) {
                            $record = $relationModel::create([
                                str_replace($rel.".",'',$key) => $value
                            ]);
                        }
                        
                            $replace = $record->id;
                        
                           
                        
                        if(is_null($replace) || empty($replace)) {
                            $tempArray = array();
                        }    
                        
                        else if(in_array($nk = strtolower($rel."_id"),$db_cols)) {
                            $tempArray[$nk] = $replace;
                            unset($tempArray[$key]);
                            $replace = '';
                        }

                       } //inner if belongs to many
                    } //outer if
                    else {
                        
                    }
                }
            
                    
            }
               
                
              

            if(empty($headers))
                $headers = array_keys($tempArray);

            if(!empty($tempArray)) {
                array_push($final_json_array,$tempArray);
            }
            $tempArray = array();

        } //foreach

        $rules = ($this->model->rules) ? : [];
        
        $csvCollection = collect($final_json_array);

        

        $input_rows = $csvCollection->chunk(3000);

        TestJob::dispatch($this->model->getTable(),$rules,$input_rows);
 
        
        
    }
  
}
