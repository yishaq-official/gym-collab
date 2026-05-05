<?php

declare(strict_types=1);

namespace Yishaq\Server\Models;

final class PaymentTransaction extends BaseModel
{
    protected function table(): string
    {
        return 'payment_transactions';
    }

    public function findByTxRef(string $txRef): ?array
    {
        return $this->db->first(
            "SELECT * FROM {$this->table()} WHERE tx_ref = :tx_ref LIMIT 1",
            ['tx_ref' => $txRef]
        );
    }

    public function create(array $payload): int
    {
        $this->db->statement(
            "INSERT INTO {$this->table()} (
                tx_ref, gateway, status, amount, currency, email, checkout_url, registration_payload, user_id, membership_id,
                created_at, updated_at
            ) VALUES (
                :tx_ref, :gateway, :status, :amount, :currency, :email, :checkout_url, :registration_payload, :user_id, :membership_id,
                NOW(), NOW()
            )",
            [
                'tx_ref' => $payload['tx_ref'],
                'gateway' => $payload['gateway'] ?? 'chapa',
                'status' => $payload['status'] ?? 'pending',
                'amount' => $payload['amount'],
                'currency' => $payload['currency'] ?? 'ETB',
                'email' => $payload['email'] ?? null,
                'checkout_url' => $payload['checkout_url'] ?? null,
                'registration_payload' => isset($payload['registration_payload'])
                    ? json_encode($payload['registration_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'user_id' => $payload['user_id'] ?? null,
                'membership_id' => $payload['membership_id'] ?? null,
            ]
        );

        return (int) $this->db->pdo()->lastInsertId();
    }

    public function updateCheckoutUrl(string $txRef, string $checkoutUrl): int
    {
        return $this->db->statement(
            "UPDATE {$this->table()}
             SET checkout_url = :checkout_url, updated_at = NOW()
             WHERE tx_ref = :tx_ref",
            [
                'tx_ref' => $txRef,
                'checkout_url' => $checkoutUrl,
            ]
        );
    }

    public function linkUserAndMembership(string $txRef, int $userId, int $membershipId): int
    {
        return $this->db->statement(
            "UPDATE {$this->table()}
             SET user_id = :user_id, membership_id = :membership_id, updated_at = NOW()
             WHERE tx_ref = :tx_ref",
            [
                'tx_ref' => $txRef,
                'user_id' => $userId,
                'membership_id' => $membershipId,
            ]
        );
    }

