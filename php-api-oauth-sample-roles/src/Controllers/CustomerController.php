<?php
namespace Src\Controllers;

class CustomerController
{
    // list all customers - fake data
    public function index($uri)
    {
        $customers = [
            [
                'name'    => 'Tester',
                'balance' => 120.00
            ],
            [
                'name'    => 'Another Tester',
                'balance' => 100.00
            ]
        ];

        $this->respondOK($customers);
    }

    // create a new customer - fake data
    public function store($uri)
    {
        $customer = [
            'name'    => 'Still A Tester',
            'balance' => 0.00
        ];

        $this->respondCreated($customer);
    }

    // charge a customer - fake data
    public function charge($uri)
    {
        $customerId = $uri[2];

        $data = [
            'customer_id' => (int) $customerId,
            'charge'      => 1.99
        ];

        $this->respondCreated($data);
    }

    private function respondOK($data)
    {
        header('HTTP/1.1 200 OK');
        echo json_encode($data);
    }

    private function respondCreated($data)
    {
        header('HTTP/1.1 201 Created');
        echo json_encode($data);
    }
}
