<?php

switch (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
    case '/redirect': header('Location: /ok', true, 302);
        break;
    case '/large': echo str_repeat('x', 1048577);
        break;
    case '/slow': sleep(7);
        echo 'ok';
        break;
    default: echo 'ok';
}
