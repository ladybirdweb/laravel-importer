<?php







Route::group(['namespace' => 'LadyBird\StreamImport\Http\Controllers'], function () {

    
    
    Route::post('import/{model}', 'ImportController@parseImport')->name('parse-import');

    Route::post('processImport/{model}','ImportController@processImport')->name('process-import');

});