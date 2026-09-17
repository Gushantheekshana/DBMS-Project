<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap/app.php';
use GymPro\V2\Services\ClassService;
require_role('TRAINER');
$actorId=(int)current_user()['UserAccountID']; $error='';
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();try{ClassService::create($actorId,$_POST);flash('success','Class submitted for administrator review.');redirect('trainer/classes.php');}catch(Throwable $exception){$error=$exception->getMessage();}}
$classes=db_all("SELECT gc.GymClassID,gc.Name,gc.StartsAt,gc.EndsAt,gc.Capacity,gc.Location,gc.Status,(SELECT COUNT(*) FROM CLASS_ENROLLMENT ce WHERE ce.GymClassID=gc.GymClassID AND ce.Status='ENROLLED') EnrolledCount FROM GYM_CLASS gc JOIN TRAINER t ON t.TrainerID=gc.TrainerID WHERE t.UserAccountID=? ORDER BY gc.StartsAt DESC",'i',[$actorId]);
$pageTitle='Trainer classes';$pageSubtitle='Create and track your schedule';include V2_ROOT.'/includes/header.php';?>
<?php if($error!==''):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><section class="panel"><h2>Create class</h2><form method="post" class="form-grid"><?=csrf_field()?><label>Name<input name="name" maxlength="120" required></label><label>Description<textarea name="description"></textarea></label><label>Starts<input type="datetime-local" name="starts_at" required></label><label>Ends<input type="datetime-local" name="ends_at" required></label><label>Capacity<input type="number" name="capacity" min="1" max="65535" required></label><label>Location<input name="location" maxlength="120"></label><button class="btn btn-primary">Submit class</button></form></section>
<div class="table-wrap"><table><thead><tr><th>Class</th><th>Schedule</th><th>Capacity</th><th>Status</th></tr></thead><tbody><?php foreach($classes as $row):?><tr><td><?=e($row['Name'])?><br><small><?=e($row['Location']??'—')?></small></td><td><?=e($row['StartsAt'])?> – <?=e($row['EndsAt'])?></td><td><?=(int)$row['EnrolledCount']?>/<?=(int)$row['Capacity']?></td><td><?=e($row['Status'])?></td></tr><?php endforeach;?><?php if(!$classes):?><tr><td colspan="4">No classes created.</td></tr><?php endif;?></tbody></table></div><?php include V2_ROOT.'/includes/footer.php';?>
