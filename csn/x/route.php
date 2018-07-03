<?php

namespace csn;

// 路由配置
Route::get('/', function() {
    Conf::init();
    Exp::end('Csn-tsyx');
});