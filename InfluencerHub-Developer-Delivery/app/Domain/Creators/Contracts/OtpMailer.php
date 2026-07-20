<?php
namespace App\Domain\Creators\Contracts;
interface OtpMailer { public function send(string $email, string $code): void; }
