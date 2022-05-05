<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CarCoopController
{
    #[Route('/')]
    public function homepage(): Response
    {
        return new Response('Bla!');
    }
}
