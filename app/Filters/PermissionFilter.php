<?php

namespace App\Filters;

use App\Services\PermissionService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to('/login')->with('error', 'Please sign in to continue.');
        }

        $module = $arguments[0] ?? null;
        $action = $arguments[1] ?? 'view';

        if ($module === null) {
            return service('response')->setStatusCode(403)->setBody('Permission filter is missing a module.');
        }

        if ((new PermissionService())->currentUserCan($module, $action)) {
            return null;
        }

        if ($request->isAJAX()) {
            return service('response')->setStatusCode(403)->setJSON([
                'message' => 'You do not have permission to perform this action.',
            ]);
        }

        return service('response')
            ->setStatusCode(403)
            ->setBody(view('errors/forbidden', ['title' => 'Access denied']));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
