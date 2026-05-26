<?php
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    public function testVerifyAcceptsCorrectPassword(): void {
        $hash = password_hash('s3cret', PASSWORD_DEFAULT);
        $this->assertTrue(Auth::verifyPassword('s3cret', $hash));
        $this->assertFalse(Auth::verifyPassword('wrong', $hash));
    }

    public function testCsrfTokensAreSingleUseAndUnpredictable(): void {
        // Reset session state for this test.
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
        $t1 = Auth::csrfToken();
        $t2 = Auth::csrfToken();
        $this->assertNotSame($t1, $t2);
        $this->assertTrue(Auth::checkCsrf($t1));
        $this->assertFalse(Auth::checkCsrf($t1)); // consumed
    }
}
