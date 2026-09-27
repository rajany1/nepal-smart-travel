<?php

namespace App\Services;

use App\Models\OriporiCoinWallet;
use App\Models\PartnerWallet;
use App\Models\CoinTransaction;
use App\Models\PartnerWalletTransaction;
use App\Models\CoinSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Central Financial Ledger Service.
 *
 * ALL wallet mutations must go through this service.
 * Wallet balances are materialized state; the ledger is the source of truth.
 *
 * NON-NEGOTIABLE RULES:
 * - Never delete financial history
 * - Never silently rewrite historical transactions
 * - All mutations must be atomic and idempotent
 * - Financial corrections use reversal/compensating transactions
 * - Historical transactions preserve their economic rates
 */
class FinancialLedgerService
{
    /**
     * Credit coins to a user's wallet.
     *
     * @param int $userId
     * @param float $amount Positive coin amount
     * @param string $type Transaction type (impression_earning, click_earning, admin_adjustment)
     * @param string $description Human-readable description
     * @param array $metadata Extra data (ad_campaign_id, report_id, rate snapshots, etc.)
     * @param string|null $idempotencyKey Optional idempotency key for dedup
     * @param int|null $actorId Admin/user who initiated (null for system)
     * @return CoinTransaction|null null if idempotent duplicate
     */
    public function creditCoins(
        int $userId,
        float $amount,
        string $type,
        string $description = '',
        array $metadata = [],
        ?string $idempotencyKey = null,
        ?int $actorId = null,
    ): ?CoinTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Credit amount must be positive, got {$amount}");
        }

        // Idempotency check
        if ($idempotencyKey) {
            $existing = CoinTransaction::where('metadata->idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                Log::info("Idempotent coin credit skipped", ['key' => $idempotencyKey, 'existing_id' => $existing->id]);
                return null;
            }
        }

        return DB::transaction(function () use ($userId, $amount, $type, $description, $metadata, $idempotencyKey, $actorId) {
            $wallet = OriporiCoinWallet::where('user_id', $userId)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = OriporiCoinWallet::create([
                    'user_id' => $userId,
                    'balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                ]);
                // Re-query with lock after create
                $wallet = OriporiCoinWallet::where('user_id', $userId)->lockForUpdate()->first();
            }

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = $balanceBefore + $amount;

            // Atomic update
            $wallet->update([
                'balance' => $balanceAfter,
                'total_earned' => $wallet->total_earned + $amount,
            ]);

            // Record in ledger
            $mergedMetadata = array_merge($metadata, [
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'actor_id' => $actorId,
            ]);
            if ($idempotencyKey) {
                $mergedMetadata['idempotency_key'] = $idempotencyKey;
            }

            $transaction = CoinTransaction::create([
                'user_id' => $userId,
                'type' => $type,
                'amount' => $amount,
                'ad_campaign_id' => $metadata['ad_campaign_id'] ?? null,
                'report_id' => $metadata['report_id'] ?? null,
                'description' => $description,
                'metadata' => $mergedMetadata,
            ]);

            return $transaction;
        });
    }

    /**
     * Debit coins from a user's wallet (for withdrawals, redemptions).
     *
     * @param int $userId
     * @param float $amount Positive coin amount to debit
     * @param string $type Transaction type (withdrawal, redemption)
     * @param string $description
     * @param array $metadata
     * @param string|null $idempotencyKey
     * @param int|null $actorId
     * @return CoinTransaction|null
     * @throws \InsufficientBalanceException
     */
    public function debitCoins(
        int $userId,
        float $amount,
        string $type,
        string $description = '',
        array $metadata = [],
        ?string $idempotencyKey = null,
        ?int $actorId = null,
    ): ?CoinTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Debit amount must be positive, got {$amount}");
        }

        // Idempotency check
        if ($idempotencyKey) {
            $existing = CoinTransaction::where('metadata->idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                Log::info("Idempotent coin debit skipped", ['key' => $idempotencyKey, 'existing_id' => $existing->id]);
                return null;
            }
        }

        return DB::transaction(function () use ($userId, $amount, $type, $description, $metadata, $idempotencyKey, $actorId) {
            $wallet = OriporiCoinWallet::where('user_id', $userId)->lockForUpdate()->first();
            if (!$wallet) {
                throw new \RuntimeException("No wallet found for user {$userId}");
            }

            $balanceBefore = (float) $wallet->balance;
            if ($balanceBefore < $amount) {
                throw new \RuntimeException("Insufficient balance: has {$balanceBefore}, needs {$amount}");
            }

            $balanceAfter = $balanceBefore - $amount;

            $wallet->update([
                'balance' => $balanceAfter,
                'total_withdrawn' => $wallet->total_withdrawn + $amount,
            ]);

            $mergedMetadata = array_merge($metadata, [
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'actor_id' => $actorId,
            ]);
            if ($idempotencyKey) {
                $mergedMetadata['idempotency_key'] = $idempotencyKey;
            }

            $transaction = CoinTransaction::create([
                'user_id' => $userId,
                'type' => $type,
                'amount' => -$amount, // Negative for debits
                'ad_campaign_id' => $metadata['ad_campaign_id'] ?? null,
                'report_id' => $metadata['report_id'] ?? null,
                'description' => $description,
                'metadata' => $mergedMetadata,
            ]);

            return $transaction;
        });
    }

    /**
     * Credit NPR to a partner's wallet.
     *
     * @param int $partnerId
     * @param float $amount Positive NPR amount
     * @param string $type Transaction type (offer_earning, topup, adjustment)
     * @param string $description
     * @param string|null $referenceType E.g. 'PartnerPayment'
     * @param int|null $referenceId
     * @param array $metadata
     * @param string|null $idempotencyKey
     * @param int|null $actorId
     * @return PartnerWalletTransaction|null
     */
    public function creditPartnerWallet(
        int $partnerId,
        float $amount,
        string $type,
        string $description = '',
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $metadata = [],
        ?string $idempotencyKey = null,
        ?int $actorId = null,
    ): ?PartnerWalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Credit amount must be positive, got {$amount}");
        }

        // Idempotency check
        if ($idempotencyKey) {
            $existing = PartnerWalletTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                Log::info("Idempotent partner credit skipped", ['key' => $idempotencyKey]);
                return null;
            }
        }

        return DB::transaction(function () use ($partnerId, $amount, $type, $description, $referenceType, $referenceId, $metadata, $idempotencyKey, $actorId) {
            $wallet = PartnerWallet::where('partner_id', $partnerId)->lockForUpdate()->first();
            if (!$wallet) {
                $wallet = PartnerWallet::create([
                    'partner_id' => $partnerId,
                    'balance' => 0,
                    'total_earned' => 0,
                    'total_withdrawn' => 0,
                ]);
                $wallet = PartnerWallet::where('partner_id', $partnerId)->lockForUpdate()->first();
            }

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = $balanceBefore + $amount;

            $wallet->update([
                'balance' => $balanceAfter,
                'total_earned' => $wallet->total_earned + $amount,
            ]);

            $transaction = PartnerWalletTransaction::create([
                'partner_id' => $partnerId,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'actor_id' => $actorId,
                'description' => $description,
                'metadata' => $metadata,
            ]);

            return $transaction;
        });
    }

    /**
     * Debit NPR from a partner's wallet (for withdrawals).
     *
     * @param int $partnerId
     * @param float $amount Positive NPR amount to debit
     * @param string $type
     * @param string $description
     * @param string|null $referenceType
     * @param int|null $referenceId
     * @param array $metadata
     * @param string|null $idempotencyKey
     * @param int|null $actorId
     * @return PartnerWalletTransaction|null
     * @throws \RuntimeException
     */
    public function debitPartnerWallet(
        int $partnerId,
        float $amount,
        string $type,
        string $description = '',
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $metadata = [],
        ?string $idempotencyKey = null,
        ?int $actorId = null,
    ): ?PartnerWalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Debit amount must be positive, got {$amount}");
        }

        if ($idempotencyKey) {
            $existing = PartnerWalletTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                Log::info("Idempotent partner debit skipped", ['key' => $idempotencyKey]);
                return null;
            }
        }

        return DB::transaction(function () use ($partnerId, $amount, $type, $description, $referenceType, $referenceId, $metadata, $idempotencyKey, $actorId) {
            $wallet = PartnerWallet::where('partner_id', $partnerId)->lockForUpdate()->first();
            if (!$wallet) {
                throw new \RuntimeException("No wallet found for partner {$partnerId}");
            }

            $balanceBefore = (float) $wallet->balance;
            if ($balanceBefore < $amount) {
                throw new \RuntimeException("Insufficient partner balance: has {$balanceBefore}, needs {$amount}");
            }

            $balanceAfter = $balanceBefore - $amount;

            $wallet->update([
                'balance' => $balanceAfter,
                'total_withdrawn' => $wallet->total_withdrawn + $amount,
            ]);

            $transaction = PartnerWalletTransaction::create([
                'partner_id' => $partnerId,
                'type' => $type,
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'actor_id' => $actorId,
                'description' => $description,
                'metadata' => $metadata,
            ]);

            return $transaction;
        });
    }

    /**
     * Get current coin_to_npr rate (snapshot for transaction recording).
     */
    public static function getCoinToNprRate(): float
    {
        return (float) CoinSetting::getValue('coin_to_npr_rate', 1);
    }

    /**
     * Convert coins to NPR using current rate.
     */
    public static function coinsToNpr(float $coins): float
    {
        return $coins * self::getCoinToNprRate();
    }

    /**
     * Convert NPR to coins using current rate.
     */
    public static function nprToCoins(float $npr): float
    {
        $rate = self::getCoinToNprRate();
        return $rate > 0 ? $npr / $rate : 0;
    }
}
