<?php

namespace App\Security;

/**
 * When a login attempt counts against someone, and when it gets them blocked.
 *
 * The rule (every number can be changed in Settings -> Security):
 *   - 5 wrong passwords block for 1 hour;
 *   - after the 3rd such block in a row (no successful login in between) the
 *     block is 1 day instead.
 * It is applied to an IP address and, separately, to a user account, so it
 * does not matter whether one machine hammers many accounts or many machines
 * hammer one account.
 *
 * Pure PHP and time is passed in, so it is unit-tested. A "state" is
 *   ['fails' => int, 'strikes' => int, 'locked_until' => ?int, 'last_fail_at' => ?int]
 * (unix timestamps): fails are the wrong attempts since the last block,
 * strikes the blocks in a row.
 */
final class LoginLockPolicy
{
    /** Blocks in a row are forgotten after this long without a failed attempt. */
    public const STRIKE_DECAY_SECONDS = 604800; // 7 days

    /** @var int */
    private $maxAttempts;

    /** @var int */
    private $blockSeconds;

    /** @var int */
    private $strikesForLongBlock;

    /** @var int */
    private $longBlockSeconds;

    public function __construct($maxAttempts = 5, $blockMinutes = 60, $strikesForLongBlock = 3, $longBlockHours = 24)
    {
        $this->maxAttempts = max(1, min(100, (int) $maxAttempts));
        $this->blockSeconds = max(1, min(1440, (int) $blockMinutes)) * 60;
        $this->strikesForLongBlock = max(1, min(20, (int) $strikesForLongBlock));
        $this->longBlockSeconds = max(1, min(720, (int) $longBlockHours)) * 3600;
    }

    public function maxAttempts()
    {
        return $this->maxAttempts;
    }

    public function blockSeconds()
    {
        return $this->blockSeconds;
    }

    public function strikesForLongBlock()
    {
        return $this->strikesForLongBlock;
    }

    public function longBlockSeconds()
    {
        return $this->longBlockSeconds;
    }

    /**
     * @return array{fails: int, strikes: int, locked_until: int|null, last_fail_at: int|null}
     */
    public static function emptyState()
    {
        return ['fails' => 0, 'strikes' => 0, 'locked_until' => null, 'last_fail_at' => null];
    }

    /**
     * @param array{fails: int, strikes: int, locked_until: int|null, last_fail_at: int|null} $state
     */
    public function isLocked(array $state, $now)
    {
        return $state['locked_until'] !== null && $state['locked_until'] > $now;
    }

    /**
     * @param array{fails: int, strikes: int, locked_until: int|null, last_fail_at: int|null} $state
     */
    public function secondsLeft(array $state, $now)
    {
        return $this->isLocked($state, $now) ? $state['locked_until'] - $now : 0;
    }

    /**
     * Wrong attempts still allowed before a block.
     *
     * @param array{fails: int, strikes: int, locked_until: int|null, last_fail_at: int|null} $state
     */
    public function attemptsLeft(array $state, $now)
    {
        return max(0, $this->maxAttempts - $this->forgotten($state, $now)['fails']);
    }

    /**
     * What a wrong password does. Attempts made while blocked change nothing.
     *
     * @param array{fails: int, strikes: int, locked_until: int|null, last_fail_at: int|null} $state
     *
     * @return array{state: array{fails: int, strikes: int, locked_until: int|null, last_fail_at: int|null}, locked: bool, seconds: int}
     */
    public function afterFailure(array $state, $now)
    {
        if ($this->isLocked($state, $now)) {
            return ['state' => $state, 'locked' => true, 'seconds' => $this->secondsLeft($state, $now)];
        }

        $state = $this->forgotten($state, $now);
        ++$state['fails'];
        $state['last_fail_at'] = $now;

        if ($state['fails'] < $this->maxAttempts) {
            return ['state' => $state, 'locked' => false, 'seconds' => 0];
        }

        ++$state['strikes'];
        $state['fails'] = 0;
        $seconds = $state['strikes'] >= $this->strikesForLongBlock ? $this->longBlockSeconds : $this->blockSeconds;
        $state['locked_until'] = $now + $seconds;

        return ['state' => $state, 'locked' => true, 'seconds' => $seconds];
    }

    /**
     * A successful login wipes the slate.
     *
     * @return array{fails: int, strikes: int, locked_until: int|null, last_fail_at: int|null}
     */
    public function afterSuccess()
    {
        return self::emptyState();
    }

    /**
     * Failures only add up inside one block-length window, and blocks in a row
     * are forgotten after a week of quiet.
     *
     * @param array{fails: int, strikes: int, locked_until: int|null, last_fail_at: int|null} $state
     *
     * @return array{fails: int, strikes: int, locked_until: int|null, last_fail_at: int|null}
     */
    private function forgotten(array $state, $now)
    {
        if ($state['last_fail_at'] === null) {
            return $state;
        }

        $quiet = $now - $state['last_fail_at'];

        if ($quiet > self::STRIKE_DECAY_SECONDS) {
            $state['strikes'] = 0;
            $state['fails'] = 0;
        } elseif ($quiet > $this->blockSeconds) {
            $state['fails'] = 0;
        }

        return $state;
    }
}
