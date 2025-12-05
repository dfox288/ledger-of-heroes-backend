<?php

namespace Tests\Unit\Exceptions\Auth;

use App\Exceptions\Auth\InvalidCredentialsException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-pure')]
class InvalidCredentialsExceptionTest extends TestCase
{
    protected $seed = false;

    #[Test]
    public function it_constructs_with_default_message(): void
    {
        $exception = new InvalidCredentialsException;

        $this->assertEquals('Invalid email or password', $exception->getMessage());
        $this->assertEquals(401, $exception->getCode());
    }

    #[Test]
    public function it_renders_proper_json_response(): void
    {
        $exception = new InvalidCredentialsException;
        $request = Request::create('/api/v1/login', 'POST');

        $response = $exception->render($request);

        $this->assertEquals(401, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('Invalid credentials', $data['message']);
        $this->assertEquals('The provided email or password is incorrect.', $data['error']);
    }
}
