<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class QueryPathController extends AbstractController
{
    // public function __construct()
    // {
    //     echo "QueryPathController";
    // }
    
    /**
     * @Route("/query/path", name="query_path")
     */
    public function index(): Response
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/QueryPathController.php',
        ]);
    }

    public function printPath(String $path) {
        echo "QueryPathController: $path";
    }
}
