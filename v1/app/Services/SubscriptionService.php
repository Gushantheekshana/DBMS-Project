<?php
declare(strict_types=1);
namespace GymPro\Services;
use DateTimeImmutable;use RuntimeException;
final class SubscriptionService
{
 public static function choosePlan(int $memberId,int $planId,int $userId): int
 {
  return \transaction(function()use($memberId,$planId,$userId){
   $plan=\db_one('SELECT * FROM MEMBERSHIP_PLAN WHERE PlanID=? AND IsActive=1 FOR UPDATE','i',[$planId]);if(!$plan)throw new RuntimeException('Membership plan is unavailable.');
   $active=\db_one("SELECT SubscriptionID FROM SUBSCRIPTION WHERE MemberID=? AND Status='ACTIVE' AND EndsAt>NOW() FOR UPDATE",'i',[$memberId]);if($active)throw new RuntimeException('You already have an active membership.');
   $due=(new DateTimeImmutable('+72 hours'))->format('Y-m-d H:i:s');
   \db_execute("UPDATE SUBSCRIPTION SET Status='CANCELLED' WHERE MemberID=? AND Status='PENDING_PAYMENT'",'i',[$memberId]);
   \db_execute("INSERT INTO SUBSCRIPTION (MemberID,PlanID,PlanNameSnapshot,PriceSnapshot,DurationMonthsSnapshot,Status,PaymentDueAt) VALUES (?,?,?,?,?,'PENDING_PAYMENT',?)",'iisdis',[$memberId,$planId,$plan['PlanName'],$plan['Price'],$plan['Duration'],$due]);
   $id=\db()->insert_id;\db_execute("UPDATE USER_ACCOUNT SET Status='PENDING_PAYMENT',PaymentDueAt=? WHERE UserID=?",'si',[$due,$userId]);return $id;
  });
 }
 public static function pay(int $subscriptionId,int $memberId,int $userId,array $card): array
 {
  $number=preg_replace('/\D/','',(string)($card['card_number']??''));$cvv=preg_replace('/\D/','',(string)($card['cvv']??''));$expiry=(string)($card['expiry']??'');$name=trim((string)($card['card_name']??''));
  if(strlen($number)<12||strlen($number)>19||strlen($cvv)<3||strlen($cvv)>4||!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/',$expiry)||$name==='')throw new RuntimeException('Enter complete demonstration card details.');
  $last4=substr($number,-4);$brand=str_starts_with($number,'4')?'Visa':(str_starts_with($number,'5')?'Mastercard':'Demo Card');$key=(string)($card['idempotency_key']??'');if(!preg_match('/^[a-f0-9]{32,64}$/',$key))throw new RuntimeException('The checkout session expired. Refresh and try again.');
  unset($number,$cvv,$expiry);
  return \transaction(function()use($subscriptionId,$memberId,$userId,$last4,$brand,$key){
   $existing=\db_one('SELECT * FROM MEMBERSHIP_PAYMENT WHERE IdempotencyKey=?','s',[$key]);if($existing)return $existing;
   $s=\db_one("SELECT * FROM SUBSCRIPTION WHERE SubscriptionID=? AND MemberID=? FOR UPDATE",'ii',[$subscriptionId,$memberId]);if(!$s||$s['Status']!=='PENDING_PAYMENT')throw new RuntimeException('This subscription is no longer awaiting payment.');
   $start=new DateTimeImmutable('today');$end=$start->modify('first day of +'.((int)$s['DurationMonthsSnapshot']).' months')->setTime(23,59,59);$ref='DEMO-'.strtoupper(bin2hex(random_bytes(8)));
   \db_execute("INSERT INTO MEMBERSHIP_PAYMENT (SubscriptionID,MemberID,Amount,Status,TransactionReference,IdempotencyKey,CardBrand,CardLast4,PaidAt) VALUES (?,?,?,'SUCCEEDED',?,?,?,?,NOW())",'iidssss',[$subscriptionId,$memberId,$s['PriceSnapshot'],$ref,$key,$brand,$last4]);
   \db_execute("UPDATE SUBSCRIPTION SET Status='ACTIVE',StartsAt=?,EndsAt=?,PaymentDueAt=NULL WHERE SubscriptionID=?",'ssi',[$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'),$subscriptionId]);
   \db_execute("UPDATE USER_ACCOUNT SET Status='ACTIVE',PaymentDueAt=NULL,ExpiredAt=NULL WHERE UserID=?",'i',[$userId]);
   \db_execute('UPDATE MEMBER SET PlanID=?,PlanStartDate=?,PlanEndDate=? WHERE MemberID=?','issi',[$s['PlanID'],$start->format('Y-m-d'),$end->format('Y-m-d'),$memberId]);
   \audit('membership.payment_succeeded','SUBSCRIPTION',$subscriptionId,['reference'=>$ref,'amount'=>$s['PriceSnapshot']]);return ['TransactionReference'=>$ref,'CardLast4'=>$last4];
  });
 }
}
