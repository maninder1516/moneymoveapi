<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251205183750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accounts (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, account_number_enc LONGTEXT NOT NULL, account_number_hash VARCHAR(64) NOT NULL, balance NUMERIC(18, 2) NOT NULL, currency VARCHAR(3) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id BIGINT UNSIGNED NOT NULL, INDEX idx_account_user (user_id), UNIQUE INDEX UNIQ_ACCOUNT_NUMBER_HASH (account_number_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE currency_rates (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, base_currency VARCHAR(3) NOT NULL, target_currency VARCHAR(3) NOT NULL, rate NUMERIC(18, 8) NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_base_currency (base_currency), INDEX idx_target_currency (target_currency), UNIQUE INDEX unique_rate (base_currency, target_currency), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ledger_entries (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, entry_type VARCHAR(20) NOT NULL, amount NUMERIC(18, 2) NOT NULL, currency VARCHAR(3) NOT NULL, note VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, transaction_id BIGINT UNSIGNED NOT NULL, account_id BIGINT UNSIGNED NOT NULL, INDEX idx_ledger_transaction (transaction_id), INDEX idx_ledger_account (account_id), INDEX idx_entry_type (entry_type), INDEX idx_ledger_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transactions (id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, reference_id VARCHAR(36) NOT NULL, amount NUMERIC(18, 2) NOT NULL, currency VARCHAR(3) NOT NULL, converted_amount NUMERIC(18, 2) DEFAULT NULL, converted_currency VARCHAR(3) DEFAULT NULL, rate_used NUMERIC(18, 8) DEFAULT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, reason VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, from_account BIGINT UNSIGNED DEFAULT NULL, to_account BIGINT UNSIGNED DEFAULT NULL, INDEX idx_reference_id (reference_id), INDEX idx_from_account (from_account), INDEX idx_to_account (to_account), INDEX idx_type (type), INDEX idx_status (status), INDEX idx_created_at (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE accounts ADD CONSTRAINT FK_CAC89EACA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ledger_entries ADD CONSTRAINT FK_E3FD73F42FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transactions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ledger_entries ADD CONSTRAINT FK_E3FD73F49B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CFB68B50B FOREIGN KEY (from_account) REFERENCES accounts (id)');
        $this->addSql('ALTER TABLE transactions ADD CONSTRAINT FK_EAA81A4CF3AE6B51 FOREIGN KEY (to_account) REFERENCES accounts (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accounts DROP FOREIGN KEY FK_CAC89EACA76ED395');
        $this->addSql('ALTER TABLE ledger_entries DROP FOREIGN KEY FK_E3FD73F42FC0CB0F');
        $this->addSql('ALTER TABLE ledger_entries DROP FOREIGN KEY FK_E3FD73F49B6B5FBA');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CFB68B50B');
        $this->addSql('ALTER TABLE transactions DROP FOREIGN KEY FK_EAA81A4CF3AE6B51');
        $this->addSql('DROP TABLE accounts');
        $this->addSql('DROP TABLE currency_rates');
        $this->addSql('DROP TABLE ledger_entries');
        $this->addSql('DROP TABLE transactions');
    }
}
