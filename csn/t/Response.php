<?php

namespace csn;

class Response
{

    function __toString()
    {
        return Route::run(Route::path(Request::path(), true)) . '';
    }

}