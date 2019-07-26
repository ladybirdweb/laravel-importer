#Laravel Importer


Flexible and reliable way to import, parse, validate and transform your csv,xsl,xslx,JSON & XML files with laravel

[![Build Status](https://scrutinizer-ci.com/g/ladybirdweb/laravel-importer/badges/build.png?b=master)](https://scrutinizer-ci.com/g/ladybirdweb/laravel-importer/build-status/master) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/ladybirdweb/laravel-importer/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/ladybirdweb/laravel-importer/?branch=master) [![Build Status](https://travis-ci.org/ladybirdweb/laravel-importer.svg?branch=master)](https://travis-ci.org/ladybirdweb/laravel-importer) [![StyleCI](https://github.styleci.io/repos/190863372/shield?branch=master)](https://github.styleci.io/repos/190863372) [![Code Intelligence Status](https://scrutinizer-ci.com/g/ladybirdweb/laravel-importer/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)

## Installation

Via Composer

``` bash
$ composer require lws/import
```

## Usage

After installation you need to add the following line to config/app.php (No Need if Laravel >= 5.5)

```
'providers' => [
	/*
     * Package Service Providers...
     */
    LWS\Import\ImportServiceProvider::class,
]
```

After setup you need migrate the database using

``` bash
$ php artisan migrate
```

Change QUEUE_CONNECTION entry in .env file

```
QUEUE_CONNECTION=database
```

The model you wish to import data to, should implement ```RelationshipsTrait``` in order to obtain the relationships associated with the model and relationships can be obtained by ```$modelObj->relationships()``` as an Array.

```php
use LWS\Import\Trait\RelationshipsTrait;

class User extends Model {
	use RelationshipsTrait;
}
```
This package has a Base Controller ```Import``` that you have to extend in your Controller

```php
use LWS\Import\Import;

class YourController extends Import
{
	//Magic
}
```


```Import``` class have the following methods:
* ```parseImport($source,Request $request)``` : It accepts a request containing the CSV file that needs to be imported as first parameter and the second parameter is name of the model,for which the importing has to be perfomed.It returns the database columns associated with the model and sample row from csv file for mapping. Name of the csv file should be ```csv_file```.
* ```processImport(Closure $callback)``` : It accepts a ```Closure``` which has the logic of mapping database columns to records in csv file.The Closure has to be written by user.
* ```getModelObject($source)``` : Accepts a model name and returns the model object if it is present in your application,otherwise return error response. 

This Package uses a ```Factory``` Class to resolve the model which sent as parameter the default namespace from which the ```Factory``` tries to resolve model classes is ```App```. If your models are in different namespace you can specify the namespace to look for resolving in ```config/import.php```.

Example Controller:
```php
<?php

namespace App\Http\Controllers;

use LWS\Import\Import;
use Illuminate\Http\Request;


class MyController extends Import
{
    public function parse(Request $request,$source)
    {
    	/*
        axios.post('http://localhost:8000/import/User',formData,{
          headers: {
            'Content-Type': 'multipart/form-data'
          }
        */
    
    
    	//accepts model name and request containing CSV(name should be csv_file)
        return $this->parseImport($source,$request);
    }

    public function processor(Request $request,$source)
    {
        //This is for Demo Only Mostly Psuedo Code You can write any logic inside closure and processImport method applies that   Closure for every line in uploaded CSV File.
        
        $fields = $request->fields; //Contains User array for mapping
        
        //ProcessImport Method Accepting Example Closure that accepts a user array containing the mapping of csv file
        
        $this->processImport(function ($rows) use ($fields){
            $model = $this->getModelObject("User"); //gets User Model Obj
            $relations = $model->relationships(); //gets Relations associated with User.
            $db_cols = $model->getFillable();
            $final_json_array = $tempArray = array();
            $rules = ($model->rules) ? : [];
            foreach($rows as $row) {
                
                foreach($fields as $key => $value) {
                    $tempArray[$value] = $row[$key]; 
                } //foreach inner

                ksort($tempArray);
                if(empty($headers))
                    $headers = array_keys($tempArray);

                if(!empty($tempArray)) {
                    array_push($final_json_array,$tempArray);
                }
                $tempArray = array();

            }

            foreach(array_chunk($final_json_array,100) as $t) {
                for($i=0;$i<count($t);$i++) {
                    $model::insert($t[$i]);
            } //for
            }
        });
    }
}

```
