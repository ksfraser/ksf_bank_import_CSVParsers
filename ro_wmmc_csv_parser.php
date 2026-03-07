<?php

/**
 * @author Kevin Fraser / ChatGPT
 * @since 20250409
 */

//we need to interpret the file and generate a new statement for each day of transactions

/**//******************************************************************************************
* Parse the CSV from Walmart.  This is a Credit card ONLY. 
*	(They don't have a retail bank side in Canada, at this time)
*
**********************************************************************************************/
class ro_wmmc_csv_parser extends parser {

	/**//**
	 * Convert an array of  CSV lines into assoc array
	 *
	 * Called by parse
	 */
	function _combine_array(&$row, $key, $header) {
  		$row = array_combine($header, $row);
	}

	/**//*********************************************************
	* Import a CSV
	*
	* @param string contents from file_get_contents
	*
	**************************************************************/
	function parse($content, $static_data = array(), $debug = true) {
	//keep statements in an array, hashed by statement-id
	//statement id is the statement date: yyyy-mm-dd-<number>-<seq>
	// as each line is processed, adjust statement data and add tranzactions
	$smts = array();

	$months = array(
		'ianuarie' => '01', 'februarie' => '02', 'martie' => '03', 'aprilie' => '04', 'mai' => '05', 'iunie' => '06',
		'iulie' => '07', 'august' => '08', 'septembrie' => '09', 'octombrie' => '10', 'noiembrie' => '11', 'decembrie' => '12',
	);

/*************************************************************
* WMMC CSV Format (2023-2024):
*
* 	"Date","Posted Date","reference number","Activity Type","status","Transaction card number","Merchant category","Merchant name","Merchant City","Merchant state/province","Merchant country","Merchant postal code/zip","Amount","rewards","name on card"
* 	"2024-01-08","2024-01-10","""55503804009004047182377""","TRANS","APPROVED","************2251","Grocery Stores and Supermarkets","WAL-MART #1050","AIRDRIE","AB","CAN","T4B 3G5","$56.34","","KEVIN FRASER"
************************************************************/


	//var_dump( $static_data );
	//split content by \n
	$lines = explode("\n", $content);

	//first line is header 
	$header = array_shift($lines);
/*******************************************************************************************
*	Over the course of 2 years, WMMC exports have had 5 different columns/headers
*
*		"Date","Activity Type","Merchant Name","Merchant Category","Amount","Rewards"
*		"Transaction Date","Activity Type","Merchant Name","Merchant Category","Amount","Rewards"
*		"Date","Posted Date","Reference Number","Activity Type","Status","Transaction Card Number","Merchant Category","Merchant Name","Merchant City","Merchant State/Province","Merchant Postal Code/Zip","Amount","Rewards"
*		"Date","Posted Date","Reference Number","Activity Type","Status","Transaction Card Number","Merchant Category","Merchant Name","Merchant City","Merchant State/Province","Merchant Country","Merchant Postal Code/Zip","Amount","Rewards","Name on Card"
*
*******************************************************************************************/
$search_arr = array();
$replace_arr = array();

$search_arr[] = "Posted Date";
$replace_arr[] = "posteddate";

$search_arr[] = "Transaction Date";
$replace_arr[] = "transdate";

$search_arr[] = "Date";
$replace_arr[] = "transdate";

$search_arr[] = "Activity Type";
$replace_arr[] = "activitytype";

$search_arr[] = "Merchant Name";
$replace_arr[] = "merchant";

$search_arr[] = "Merchant Category";
$replace_arr[] = "category";

$search_arr[] = "Amount";
$replace_arr[] = "amount";

$search_arr[] = "Rewards";
$replace_arr[] = "rewards";

$search_arr[] = "Reference Number";
$replace_arr[] = "referencenumber";

$search_arr[] = "Status";
$replace_arr[] = "status";

$search_arr[] = "Transaction Card Number";
$replace_arr[] = "cardnumber";

$search_arr[] = "Merchant City";
$replace_arr[] = "city";

$search_arr[] = "Merchant State/Province";
$replace_arr[] = "province";

$search_arr[] = "Merchant Postal Code/Zip";
$replace_arr[] = "postalcode";

$search_arr[] = "Merchant Country";
$replace_arr[] = "country";

$search_arr[] = "Name on Card";
$replace_arr[] = "nameoncard";

	$header = strtolower( $header );
	//var_dump( $header );
	$header_arr = str_getcsv( $header );
	//$header_arr = explode( ",", $header );
	var_dump( $header_arr );

	//current transaction
	$trz = null;
	$trz_line = '';
	//last TRF transaction
	$last_trz = null;
	
	//parse lines


	foreach($lines as $line) {
		if (strlen($line) == 0)
		continue;

		//echo "----------------------------------------------------\n";
			echo "debug: line: $line\n";

		$linedata = str_getcsv($line);
		var_dump( $linedata );
		$linedata = array_combine( $header_arr, $linedata );
		var_dump( $linedata );

		//if exists and has some date format => it is a $sid
		if(!empty($linedata["date"])) {
		$sid = $linedata["date"];
		}

		//if smtid exists in results, add to this statement else create new statement
		if (empty($smts[$sid])) {
		$smts[$sid] = new statement;
		if( isset( $static_data['bank_name'] ) )
		{
			$smts[$sid]->bank = $static_data['bank_name'];
		}
		else
		{
			$smts[$sid]->bank = 'WMMC';	//WM has renamed their banking a couple of times.  Currently on their URL is FAIRSTONE (was DUOBANK).  Used to be WMFS.  And something else some other time.
		}
		//get additional info from static_data
		$smts[$sid]->account = $static_data['account'];		//This is an account number string i.e. from the bank.  For walmart, they send it as ************2251
			//Someone might want to extend this if you have multiple cards on the account,
			//and you want to insert each person's spending into a different GL
		$smts[$sid]->currency = $static_data['currency'];
		$smts[$sid]->timestamp = $sid;
		$smts[$sid]->startBalance = '0';
		$smts[$sid]->endBalance = '0';
		$smts[$sid]->number = '00000';
		$smts[$sid]->sequence = '0';
		$smts[$sid]->statementId = "{$sid}-{$smts[$sid]->number}-{$smts[$sid]->sequence}";

		echo "debug: adding a statement with sid=$sid\n";
		} else {
			echo "debug: statement exists for sid=$sid\n";
		}

		//state machine
		// in transaction && new transaction indicator => close transaction
		if ($trz && !empty($linedata["date"]) ) {
		if ($debug) {
			echo "debug: closing transaction {$trz->valueTimestamp}\n";
			echo "debug: trz_line=$trz_line\n";
			$trz->dump();
		}
		$smts[$trz->valueTimestamp]->addTransaction($trz);

		if ($trz->transactionType != 'COM')
			$last_trz = $trz;
		if ($trz->valueTimestamp != $sid)
			$last_trz = null;

		$trz = null;
		$trz_line = '';
		}
		
		// not in transaction && new transaction indicator => open transaction and parse line 1
		if (!$trz && !empty($linedata['date'])) {
			if ($debug) echo "debug: adding new transaction....\n";
			$trz = new transaction;

			//transactionDC & amount
			var_dump( $linedata['amount'] );
//TRF for Transactions
//COM for fees - ATM fees, commissions, admin fees.
			if( strncmp( $linedata['amount'], "-", 1 ) == 0 )
			{
				//This is a payment or refund
				//first char is -
	
				if( false !== strpos( $linedata['merchant name'], "PAYMENT" ) )
				{
					//PAYMENT, comes from a Bank
					$trz->transactionDC = 'B';
					$trz->transactionType = 'TRF';
				}		
				else
				{
					//IF Not PAYMENT - REFUND
					$trz->transactionDC = 'C';
	  				$trz->transactionType = 'TRF';
				}
			}
			else
			{
				//Charge
				$trz->transactionDC = 'D';
	  			$trz->transactionType = 'TRF';
			}
			$amount = floatval(preg_replace('/[^\d\.]/', '', $linedata['amount']));
			var_dump( $amount );
			$trz->transactionAmount = $amount;
			 
	  		$trz->valueTimestamp = $sid;		//Trans Date
	  		$trz->entryTimestamp = $linedata['posted date'];		//Posted Date
			$trz->transactionTitle2 = $linedata['merchant city'];	//City
			$trz->transactionTitle3 = $linedata['merchant state/province'];	//state
			$trz->transactionTitle4 = $linedata['merchant country'];	//country
			$trz->address = $linedata['merchant city'] . "\n" . $linedata['merchant state/province'] . "\n" . $linedata['merchant country'] . "\n" ;
			//Even if it isn't a Canadian Merchant, the currency would be CAD...
/*
			if( "CAN" == $linedata['merchant country'] )
			{
				$smts[$sid]->currency = $trz->currency = 'CAD';
			}
*/
				$smts[$sid]->currency = $trz->currency = 'CAD';
		
			$trz->transactionTitle5 = 	$linedata['merchant postal code/zip'] . "  ";	//postal code
			$trz->transactionTitle6 = 	$linedata['merchant category'] . "; ";	//category
			$trz->category = 		$linedata['merchant category'] . "; ";	//category
			$trz->transactionTitle7 = 	$linedata['rewards'] . " ";	//rewards
			$trz->transactionTitle1 = 	$linedata['merchant name'];	//Merchant
			$trz->merchant = 		$linedata['merchant name'];	//Merchant
			$trz->transactionCode = 	$linedata['reference number'];		//reference number
			if( strlen( $trz->transactionCode ) < 1 )
			{
/*  **str_contains is a php 8
				if( str_contains( $linedata['merchant name'], "INTEREST" ) )
				{
			//		//$trz->transactionCode = $trz->entryTimestamp;
			//		////$trz->transactionCode = $trz->entryTimestamp . "-" . $linedata['merchant name'];
				}
**/
				preg_match( '/INTER/', $linedata['merchant name'], $matches );
				var_dump( $matches );
				if( count( $matches ) > 0 )
				{
					$trz->transactionCode = $trz->entryTimestamp . "-" . $linedata['merchant name'];
				}

			}
			$trz->reference = 		$linedata['reference number'];		//reference number
			$trz->transactionCodeDesc = 	$linedata['status'];	//status
			$trz->status = 			$linedata['status'];	//status
			//$trz->account = 		$static_data['account'];
			/*
			$Merchant = explode( " ", $linedata['merchant name'] );
					$trz->account = $Merchant[0];			//Merchant.  These become supplier payments, so we want to match against the merchant
			*/
			$inc = include_once( 'include.inc' );
			if( $inc )
			{
				$trz->account = shorten_bankAccount_names( $linedata['merchant name'] );			
			}
			else
			{
				$trz->account = $linedata['merchant name'];			
			}
				$trz->accountName = $linedata['merchant name'];			
	
			//$trz->account = $linedata['card number'];			//card
			$trz->accountName1 = $linedata['merchant name'] . " ";	//Merchant Full name
			$trz->accountName2 = $linedata['transaction card number'] . " / " . $linedata['name on card'];		//card# + name on card
			//add timestamp
			///$trz->valueTimestamp = $trz->entryTimestamp = $sid;
			$trz->memo = $trz->transactionTitle1 . " " . $trz->transactionTitle2 . ", " . $trz->transactionTitle3 . ";" . $trz->transactionTitle4 . " " . $trz->transactionTitle5 . ";" . $trz->transactionTitle6;
			$trz->transactionTitle = $trz->transactionTitle1 . ";" . $trz->transactionTitle2 . ";" . $trz->transactionTitle6;

			var_dump( $trz );
	
			//debug
			$trz_line = $line;
			//end of loop
			continue;
		}
	}
	//parsing ended, cleanup
	if ($trz)
		$smts[$sid]->addTransaction($trz);

	//time to return
	return $smts;
	}

}



