<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class RateLimitFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $cache = Services::cache();
        $ip = $request->getIPAddress();
        $key = "rate_limit_{$ip}";
        
        $count = $cache->get($key) ?? 0;
        
        if ($count >= 100) { // 100 requests per hour
            return Services::response()
                ->setStatusCode(429)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Rate limit exceeded. Please try again later.',
                    'retry_after' => 3600
                ]);
        }
        
        $cache->save($key, $count + 1, 3600); // 1 hour
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Not needed
    }
}
