<?php

namespace AppTests\Unit;

use App\Security\LoginLockPolicy;
use PHPUnit\Framework\TestCase;

final class LoginLockPolicyTest extends TestCase
{
    private const NOW = 1700000000;

    /**
     * Applies $n wrong attempts one second apart and returns the last result.
     *
     * @param array{fails: int, strikes: int, locked_until: int|null, last_fail_at: int|null} $state
     *
     * @return array{state: array, locked: bool, seconds: int}
     */
    private function failTimes(LoginLockPolicy $policy, array $state, int $n, int $at)
    {
        $result = ['state' => $state, 'locked' => false, 'seconds' => 0];
        for ($i = 0; $i < $n; ++$i) {
            $result = $policy->afterFailure($result['state'], $at + $i);
        }

        return $result;
    }

    public function testFiveWrongPasswordsBlockForOneHour(): void
    {
        $policy = new LoginLockPolicy();

        $four = $this->failTimes($policy, LoginLockPolicy::emptyState(), 4, self::NOW);
        $this->assertFalse($four['locked']);
        $this->assertSame(1, $policy->attemptsLeft($four['state'], self::NOW + 4));

        $five = $policy->afterFailure($four['state'], self::NOW + 4);
        $this->assertTrue($five['locked']);
        $this->assertSame(3600, $five['seconds']);
        $this->assertSame(self::NOW + 4 + 3600, $five['state']['locked_until']);
        $this->assertSame(1, $five['state']['strikes']);
        $this->assertSame(0, $five['state']['fails'], 'the counter starts again after a block');
    }

    public function testTheBlockLiftsByItself(): void
    {
        $policy = new LoginLockPolicy();
        $blocked = $this->failTimes($policy, LoginLockPolicy::emptyState(), 5, self::NOW)['state'];
        $until = $blocked['locked_until'];

        $this->assertTrue($policy->isLocked($blocked, $until - 1));
        $this->assertSame(1, $policy->secondsLeft($blocked, $until - 1));
        $this->assertFalse($policy->isLocked($blocked, $until));
        $this->assertSame(0, $policy->secondsLeft($blocked, $until));
        $this->assertSame(5, $policy->attemptsLeft($blocked, $until), 'a full set of attempts again');
    }

    public function testThirdBlockInARowIsOneDay(): void
    {
        $policy = new LoginLockPolicy();
        $state = LoginLockPolicy::emptyState();
        $at = self::NOW;
        $durations = [];

        for ($block = 1; $block <= 4; ++$block) {
            $r = $this->failTimes($policy, $state, 5, $at);
            $this->assertTrue($r['locked']);
            $durations[] = $r['seconds'];
            $state = $r['state'];
            $at = $state['locked_until'] + 1; // wait it out, then try again
        }

        $this->assertSame([3600, 3600, 86400, 86400], $durations, '5 wrong x3 in a row = 1 day, and it stays at a day until something changes');
        $this->assertSame(4, $state['strikes']);
    }

    public function testAttemptsWhileBlockedChangeNothing(): void
    {
        $policy = new LoginLockPolicy();
        $blocked = $this->failTimes($policy, LoginLockPolicy::emptyState(), 5, self::NOW);

        $again = $policy->afterFailure($blocked['state'], self::NOW + 100);

        $this->assertSame($blocked['state'], $again['state']);
        $this->assertTrue($again['locked']);
        $this->assertSame($blocked['state']['locked_until'] - (self::NOW + 100), $again['seconds']);
    }

    public function testASuccessfulLoginWipesTheSlate(): void
    {
        $policy = new LoginLockPolicy();
        $two = $this->failTimes($policy, LoginLockPolicy::emptyState(), 2, self::NOW)['state'];
        $this->assertSame(2, $two['fails']);

        $this->assertSame(LoginLockPolicy::emptyState(), $policy->afterSuccess());

        // ...so blocks in a row start counting from zero again.
        $state = $policy->afterSuccess();
        $r = $this->failTimes($policy, $state, 5, self::NOW + 10);
        $this->assertSame(1, $r['state']['strikes']);
        $this->assertSame(3600, $r['seconds']);
    }

    public function testFailuresOnlyAddUpWithinTheWindow(): void
    {
        $policy = new LoginLockPolicy();
        $four = $this->failTimes($policy, LoginLockPolicy::emptyState(), 4, self::NOW)['state'];

        // A fifth wrong password more than an hour after the last one starts a new count.
        $late = $policy->afterFailure($four, self::NOW + 3 + 3601);

        $this->assertFalse($late['locked']);
        $this->assertSame(1, $late['state']['fails']);
        $this->assertSame(4, $policy->attemptsLeft($late['state'], self::NOW + 3 + 3601));
    }

    public function testBlocksInARowAreForgottenAfterAWeekOfQuiet(): void
    {
        $policy = new LoginLockPolicy();
        $state = LoginLockPolicy::emptyState();
        for ($i = 0, $at = self::NOW; $i < 2; ++$i) {
            $state = $this->failTimes($policy, $state, 5, $at)['state'];
            $at = $state['locked_until'] + 1;
        }
        $this->assertSame(2, $state['strikes']);

        $weekLater = $state['locked_until'] + LoginLockPolicy::STRIKE_DECAY_SECONDS + 10;
        $r = $this->failTimes($policy, $state, 5, $weekLater);

        $this->assertSame(1, $r['state']['strikes'], 'back to a first offence');
        $this->assertSame(3600, $r['seconds']);
    }

    public function testTheNumbersCanBeChanged(): void
    {
        $policy = new LoginLockPolicy(3, 10, 2, 2); // 3 wrong = 10 minutes; 2nd in a row = 2 hours

        $first = $this->failTimes($policy, LoginLockPolicy::emptyState(), 3, self::NOW);
        $this->assertSame(600, $first['seconds']);

        $second = $this->failTimes($policy, $first['state'], 3, $first['state']['locked_until'] + 1);
        $this->assertSame(7200, $second['seconds']);
        $this->assertSame(3, $policy->maxAttempts());
        $this->assertSame(600, $policy->blockSeconds());
        $this->assertSame(2, $policy->strikesForLongBlock());
        $this->assertSame(7200, $policy->longBlockSeconds());
    }

    public function testNonsenseSettingsAreClamped(): void
    {
        $policy = new LoginLockPolicy(0, 0, -5, 0);
        $this->assertSame(1, $policy->maxAttempts());
        $this->assertSame(60, $policy->blockSeconds());
        $this->assertSame(1, $policy->strikesForLongBlock());
        $this->assertSame(3600, $policy->longBlockSeconds());

        $big = new LoginLockPolicy(100000, 100000, 100000, 100000);
        $this->assertSame(100, $big->maxAttempts());
        $this->assertSame(1440 * 60, $big->blockSeconds());
        $this->assertSame(720 * 3600, $big->longBlockSeconds());
    }
}
