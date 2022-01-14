   
<?php
/**
* Created by : Gbenga Ogunbule
* Location : Ijebu Ode
* Date : 14/01/22
* Time : 15:56
*/
// Initialize the session
session_start();

//connect to database
require('connect.php');

if (isset($_POST['withdraw']))  {
    $withdraw = filter_var($_POST["withdraw_amount"], FILTER_SANITIZE_NUMBER_INT, FILTER_FLAG_STRIP_HIGH);
    //echo $trader_referral_bonus_received_n. "<br/>";
    if($withdraw <= $trader_referral_bonus_received_n){
        $wa = $withdraw * 100; // wa means Withdraw Amount
        // Let the transaction go through
        // Connect to the payment gateway (Paystack) for transactionto go through
        //echo $user_account_number . " " . $user_account_code;
        // Verify the account number
        //echo "connecting to paystack<br/>";
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "https://api.paystack.co/bank/resolve?account_number=$user_account_number&bank_code=$user_account_code",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => array(
    "Authorization: Bearer sk_live_5f256bfcecd0840cbe9c1e8c63e0a4013beac23b",
    "Cache-Control: no-cache",
    ),
));
$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);
if ($err) {
    echo "cURL Error #:" . $err;
} else {
    //echo "$response<br/>";
    // Create a transfer receipt
$url = "https://api.paystack.co/transferrecipient";
  $fields = [
    'type' => "nuban",
    'name' => "$trader_first_name $trader_last_name",
    'account_number' => "$user_account_number",
    'bank_code' => "$user_account_code",
    'currency' => "NGN"
  ];
  $fields_string = http_build_query($fields);
  //open connection
  $ch = curl_init();
  
  //set the url, number of POST vars, POST data
  curl_setopt($ch,CURLOPT_URL, $url);
  curl_setopt($ch,CURLOPT_POST, true);
  curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer sk_live_5f256bfcecd0840cbe9c1e8c63e0a4013beac23b",
    "Cache-Control: no-cache",
  ));
  
  //So that curl_exec returns the contents of the cURL; rather than echoing it
  curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 
  
  //execute post
  $result = curl_exec($ch);
  //var_dump($result);
  $matth = json_decode($result);
  //print_r($matth);
  //echo "Creating a transfer receipt<br/>";
  //echo $matth->data->recipient_code . "<br/>";

  // Initiate a transfer
  $url = "https://api.paystack.co/transfer";
  $fields = [
    'source' => "balance",
    'amount' => $wa,
    'recipient' => $matth->data->recipient_code,
    'reason' => "Cybertrade Technology"
  ]; 
  $fields_string = http_build_query($fields);
  //open connection
  $ch = curl_init();
  
  //set the url, number of POST vars, POST data
  curl_setopt($ch,CURLOPT_URL, $url);
  curl_setopt($ch,CURLOPT_POST, true);
  curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Authorization: Bearer sk_live_5f256bfcecd0840cbe9c1e8c63e0a4013beac23b",
    "Cache-Control: no-cache",
  ));
  
  //So that curl_exec returns the contents of the cURL; rather than echoing it
  curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 
  
  //execute post
  $result2 = curl_exec($ch);
  
  //echo "Transfered successfully<br/>";
  // Convert returned statement to JSON encode
  $tranx = json_decode($result2);
  
  // Check if it returened a status call and if yes,proceed
  if(!$tranx->status){
	// there was an error from the API
	die('API returned error: ' . $tranx->message);
  }
  
  // Check if transaction is successful
  $success = $tranx->data->status;
  if('success' == $tranx->data->status || 'pending' == $tranx->data->status) {
      //echo $success;
      $trans = serialize($tranx);
      
      $update = "UPDATE members SET referral_bonus_received=referral_bonus_received-$withdraw WHERE phone_number={$_SESSION['phone_number']};";
      $updateDetails= mysqli_query($con,$update);
      //$result = mysqli_multi_query($con, $update);
      //echo var_dump($tranx);
        // Insert transaction details into the db table
        //$updateInsert.="INSERT INTO transaction_history (reference,intergration,amount,receipient,statuses,request,trans_id) VALUE ($reference,$integration,$withdraw,$recipient,$status,$request,$transId);";
        $insert="INSERT INTO transaction_history (reference,intergration,amount,receipient,statuses,request,trans_id, phone_number) VALUE (?,?,?,?,?,?,?,?);";
        //$detail= mysqli_query($con,$insertDetails);
        $stmt = $con->prepare($insert);
        $stmt->bind_param("ssssssss", $tranx->data->reference, $tranx->data->integration, $withdraw, $tranx->data->recipient, $tranx->data->status, $tranx->data->request, $tranx->data->id, $_SESSION['phone_number']);
        $stmt->execute();
       
        // Check if it successfully insert into the DB and redirect if successful
        if($stmt==true){
          header("location:dashboard_n.php");
          exit();
         //echo "Real";
        }
  }else{
     echo "Just not working"; 
  }//end of if transaction successful
}
    } else {
        // Reject the withdrawal transaction
        //Display an error to the user that the amount to withdraww is not possible due to low balance
        $_SESSION['withdrawError'] = "insufficient fund.";
        //echo "Total amount is " . $withdraw . "<br/>";
    }
    
 }elseif( $trader_referral_bonus_received_n < $withdrawal_limit_naira)
 {
	echo '<b>Error!</b> <font color="gray">Your wallet balance is too low for this request, <br> a minimum balance of </font><font color="gray"> <b>₦'.number_format($withdrawal_limit_naira,2).' </b></font> is require to process this order'; 
}  
?>


<!----------------include header--------------------->
<title>Cash Payout Request</title>
 <?php include('header.php'); ?>
<!---------------end header--------------------->

<?php
if(isset($_SESSION['withdrawError'])):?>
    <h3><?=$_SESSION['withdrawError']?></h3>
<?php    unset($_SESSION['withdrawError']);
 endif;

?>
   <div align="center"> <font color="gree">Available Bal:  <?php echo number_format($trader_referral_bonus_received_n,2)?></font>  <br>
	<form enctype="multipart/form-data" action= "" method="post" autocomplete="off">
<!------------------Withdrwal Amount----------------->			
<div style="color:red; font-size:17px; text:align:center;margin-top:15px;"> <?php echo @$firstname_entry_error;?> </div>
<input type="text" name="withdraw_amount" placeholder="Withdrawal Amount"><br/>
<input type="submit" name="withdraw" value="Withdraw">
<!------------------End withdrawal Amount----------------->
</form>
  
</div>
    
<?php  

?>
 
 <!----------------include footer--------------------->
 <?php include('footer.php'); ?>
 <!----------------end of footer--------------------->
