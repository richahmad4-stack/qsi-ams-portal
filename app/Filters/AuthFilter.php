<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('is_logged_in')) {
            return null;
        }

        if ($request->isAJAX()) {
            return service('response')->setStatusCode(401)->setJSON([
                'message' => 'Authentication is required.',
            ]);
        }

        return redirect()->to('/login')->with('error', 'Please sign in to continue.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
