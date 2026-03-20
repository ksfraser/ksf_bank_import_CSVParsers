<?php
/**
 * Temporary script to check what Xdebug considers coverable lines
 * with branch coverage enabled (what Devsense uses).
 */
require 'vendor/autoload.php';

xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);

// Exercise all Entity methods
$b = new Parsers\Entities\BankAccount(['accountId' => '123', 'accountType' => 'CHECKING']);
$b->getDisplayName();
$b->toArray();

$bal = new Parsers\Entities\Balance(100.0, 'LEDGER', '2024-01-01');
$bal->toArray();

$cur = new Parsers\Entities\Currency('CAD', 1.0);
$cur->__toString();
$cur->toArray();

$tx = new Parsers\Entities\Transaction(['date' => '2024-01-01', 'payee' => 'Test', 'amount' => -50.0]);
$tx->addSplit(new Parsers\Entities\Transaction(['amount' => -25.0]));
$tx->getSplits();
$tx->isSplit();
$payee = new Parsers\Entities\Payee(['name' => 'Test', 'city' => 'Calgary']);
$tx->setPayee($payee);
$tx->getPayee();

$st = new Parsers\Entities\Statement();
$st->addTransaction($tx);
$st->getTransactions();

$cov = xdebug_get_code_coverage();

$entityFiles = ['BankAccount', 'Balance', 'Currency', 'Transaction', 'Statement'];
foreach ($cov as $file => $lines) {
    foreach ($entityFiles as $entity) {
        if (strpos($file, $entity . '.php') !== false) {
            echo "\n=== $entity ===\n";
            $covered = 0;
            $coverable = 0;
            foreach ($lines as $line => $status) {
                if (!is_int($line)) continue; // skip metadata keys like 'lines', 'functions'
                $coverable++;
                $label = $status === 1 ? 'COVERED' : ($status === -1 ? 'NOT COVERED' : 'DEAD');
                if ($status === 1) $covered++;
                echo "  Line $line: $label\n";
            }
            if ($coverable > 0) {
                echo "  Coverage: $covered/$coverable (" . round($covered/$coverable*100, 1) . "%)\n";
            }
        }
    }
}
