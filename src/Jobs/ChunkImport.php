<?php

namespace LadyBird\StreamImport\Jobs;

use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use LadyBird\StreamImport\Factory;
use Illuminate\Queue\SerializesModels;
use LadyBird\StreamImport\Jobs\TestJob;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Validator;


class ChunkImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $source;
    protected $chunk;
    protected $model;
    protected $request;
    public $timeout = 120;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($chunk,$source,Request $request)
    {
        $this->chunk = $chunk;
        $this->source = $source;
        $this->request = $request->all();
        
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
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


        foreach($this->chunk as $row) {

            

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
        
        // $csvCollection = collect($final_json_array);


        // $input_rows = $csvCollection->chunk(1000);

        // TestJob::dispatch($this->model->getTable(),$rules,$input_rows);


        foreach(array_chunk($final_json_array,1000) as $t) {
            
            for($i=0;$i<count($t);$i++) {
                
                $validator = Validator::make($t[$i],$rules);

                if($validator->fails()) {
                    
                    // return response()->json($validator->messages()->getMessages(), 404);
                    //Storage::disk('local')->put('return.json',[404 => $validator->messages()->getMessages()]);
                }

                else {
                    $this->model::insert($t[$i]);
                    
                }

            } //for
            
        } //foreach

        

    } //handle
  
} //class
