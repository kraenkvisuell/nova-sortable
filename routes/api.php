<?php

use Illuminate\Support\Facades\Route;

Route::post('sort/{resource}/update-order', 'SortableController@updateOrder');
Route::post('sort/{resource}/change-position', 'SortableController@changePosition');
Route::post('sort/{resource}/move-to-start', 'SortableController@moveToStart');
Route::post('sort/{resource}/move-to-end', 'SortableController@moveToEnd');
