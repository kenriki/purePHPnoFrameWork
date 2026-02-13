<?php

require_once __DIR__ . '/controllers/PageController.php';
require_once __DIR__ . '/controllers/HomeController.php';
require_once __DIR__ . '/controllers/SampleController.php';
require_once __DIR__ . '/controllers/Sample1Controller.php';

function route($page) {

    switch ($page) {

        case 'home':
            return (new HomeController())->show();

        case 'sample':
            return (new SampleController())->show();

        case 'sample1':
            return (new Sample1Controller())->show();

        default:
            // JSONページ（固定ページ）
            return (new PageController())->render($page);
    }
}

// function route($path) {
//     switch ($path) {

//         case 'home':
//             $controller = new PageController();
//             return $controller->render('home');

//         case 'product':
//             $controller = new ProductController();
//             return $controller->list();

//         case 'form':
//             $controller = new FormController();
//             return $controller->show();

//         case 'modal':
//             $controller = new ModalController();
//             return $controller->show();

//         default:
//             http_response_code(404);
//             echo "Page not found";
//     }
// }

?>