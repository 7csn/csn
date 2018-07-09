<?php

namespace csn;

// 路由配置
Route::get('/', function() {
    Config::init();
    Csn::close('Csn-tsyx');
});