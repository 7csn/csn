<?php

namespace csn;

// 路由配置
Route::get('/', function() {
    Conf::init();
    Csn::close('Csn-tsyx')->E();
});