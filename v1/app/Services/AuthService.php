<?php
declare(strict_types=1);
namespace GymPro\Services;

use GymPro\Mail\Mailer;
use RuntimeException;

final class AuthService
{
    public static function registerMember(array $data): int
    {
        $email=strtolower(trim($data['email']??'')); self::validateAccount($email,(string)($data['password']??''));
        foreach(['name','nic','phone'] as $field) if(trim((string)($data[$field]??''))==='') throw new RuntimeException(ucfirst($field).' is required.');
        if(\db_one('SELECT UserID FROM USER_ACCOUNT WHERE Email=?','s',[$email])) throw new RuntimeException('An account already uses this email.');
        $password=(string)$data['password'];
        $userId=\transaction(function() use($data,$email,$password) {
            \db_execute('INSERT INTO MEMBER (Name,NIC,DOB,Gender,Email,Address,PhoneNo,RegDate,CreatedAt) VALUES (?,?,?,?,?,?,?,CURDATE(),NOW())','sssssss',[trim($data['name']),trim($data['nic']),$data['dob']?:null,$data['gender']?:null,$email,trim($data['address']??''),trim($data['phone'])]);
            $memberId=\db()->insert_id;
            \db_execute("UPDATE MEMBER SET MemberNumber=CONCAT('MEM-',LPAD(MemberID,6,'0')) WHERE MemberID=?",'i',[$memberId]);
            $hash=password_hash($password,PASSWORD_DEFAULT);
            \db_execute("INSERT INTO USER_ACCOUNT (Email,PasswordHash,Role,Status,MemberID) VALUES (?,?,'MEMBER','PENDING_EMAIL',?)",'ssi',[$email,$hash,$memberId]);
            return \db()->insert_id;
        });
        self::sendVerification($userId,$email); return $userId;
    }

    public static function registerTrainer(array $data, array $file): int
    {
        $email=strtolower(trim($data['email']??'')); self::validateAccount($email,(string)($data['password']??''));
        foreach(['name','phone','specialization'] as $field) if(trim((string)($data[$field]??''))==='') throw new RuntimeException(ucfirst($field).' is required.');
        if(\db_one('SELECT UserID FROM USER_ACCOUNT WHERE Email=?','s',[$email])) throw new RuntimeException('An account already uses this email.');
        $document=self::validateDocument($file);
        $stored=bin2hex(random_bytes(20)).'.'.$document['extension'];
        $target=ROOT_PATH.'/storage/trainer-documents/'.$stored;
        if(!move_uploaded_file($file['tmp_name'],$target)) throw new RuntimeException('Unable to store qualification document.');
        try {
            $password=(string)$data['password'];
            $userId=\transaction(function() use($data,$email,$password,$document,$stored,$target) {
                \db_execute("INSERT INTO TRAINER (Name,PhoneNo,Email,Experience,Bio,Specialization,ApplicationStatus) VALUES (?,?,?,?,?,?,'PENDING_EMAIL')",'sssiss',[trim($data['name']),trim($data['phone']),$email,(int)($data['experience']??0),trim($data['bio']??''),trim($data['specialization'])]);
                $trainerId=\db()->insert_id;
                $hash=password_hash($password,PASSWORD_DEFAULT);
                \db_execute("INSERT INTO USER_ACCOUNT (Email,PasswordHash,Role,Status,TrainerID) VALUES (?,?,'TRAINER','PENDING_EMAIL',?)",'ssi',[$email,$hash,$trainerId]);
                $userId=\db()->insert_id;
                \db_execute('INSERT INTO TRAINER_DOCUMENT (TrainerID,StoredName,OriginalName,MimeType,FileSize,Sha256) VALUES (?,?,?,?,?,?)','isssis',[$trainerId,$stored,$document['original'],$document['mime'],$document['size'],hash_file('sha256',$target)]);
                return $userId;
            });
        } catch (\Throwable $e) { @unlink($target); throw $e; }
        self::sendVerification($userId,$email); return $userId;
    }

    public static function sendVerification(int $userId,string $email): void
    {
        $recent=\db_one('SELECT TokenID FROM EMAIL_VERIFICATION_TOKEN WHERE UserID=? AND CreatedAt>DATE_SUB(NOW(),INTERVAL 2 MINUTE)','i',[$userId]);
        if($recent) return;
        $token=bin2hex(random_bytes(32)); $hash=hash('sha256',$token);
        \db_execute('INSERT INTO EMAIL_VERIFICATION_TOKEN (UserID,TokenHash,ExpiresAt) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 60 MINUTE))','is',[$userId,$hash]);
        $url=\base_url('auth/verify.php?token='.urlencode($token));
        Mailer::send($email,'Verify your GymPro account','<h1>Verify your email</h1><p>Your link expires in 60 minutes.</p><p><a href="'.\e($url).'">Verify account</a></p>','Verify your GymPro account: '.$url);
    }

    public static function verifyEmail(string $token): array
    {
        return \transaction(function() use($token) {
            $row=\db_one('SELECT t.TokenID,t.UserID,u.Role,u.TrainerID FROM EMAIL_VERIFICATION_TOKEN t JOIN USER_ACCOUNT u ON u.UserID=t.UserID WHERE t.TokenHash=? AND t.ConsumedAt IS NULL AND t.ExpiresAt>NOW() FOR UPDATE','s',[hash('sha256',$token)]);
            if(!$row) throw new RuntimeException('This verification link is invalid or expired.');
            \db_execute('UPDATE EMAIL_VERIFICATION_TOKEN SET ConsumedAt=NOW() WHERE TokenID=?','i',[$row['TokenID']]);
            if($row['Role']==='MEMBER') \db_execute("UPDATE USER_ACCOUNT SET EmailVerifiedAt=NOW(),PaymentDueAt=DATE_ADD(NOW(),INTERVAL 72 HOUR),Status='PENDING_PAYMENT' WHERE UserID=?",'i',[$row['UserID']]);
            else { \db_execute("UPDATE USER_ACCOUNT SET EmailVerifiedAt=NOW(),Status='PENDING_APPROVAL' WHERE UserID=?",'i',[$row['UserID']]); \db_execute("UPDATE TRAINER SET ApplicationStatus='PENDING_APPROVAL' WHERE TrainerID=?",'i',[$row['TrainerID']]); }
            return $row;
        });
    }

    public static function login(string $email, string $password, ?string $expectedRole = null): array
    {
        $roles = ['MEMBER', 'TRAINER', 'RECEPTIONIST', 'SUPER_ADMIN'];
        if ($expectedRole !== null && !in_array($expectedRole, $roles, true)) {
            throw new RuntimeException('Invalid sign-in portal.');
        }

        $email = strtolower(trim($email));
        $user = $expectedRole === null
            ? \db_one('SELECT * FROM USER_ACCOUNT WHERE Email=? AND AnonymizedAt IS NULL', 's', [$email])
            : \db_one('SELECT * FROM USER_ACCOUNT WHERE Email=? AND Role=? AND AnonymizedAt IS NULL', 'ss', [$email, $expectedRole]);

        if (!$user || ($user['LockedUntil'] && strtotime($user['LockedUntil']) > time()) || !password_verify($password, $user['PasswordHash'])) {
            if ($user) {
                \db_execute('UPDATE USER_ACCOUNT SET FailedLoginCount=FailedLoginCount+1,LockedUntil=CASE WHEN FailedLoginCount>=4 THEN DATE_ADD(NOW(),INTERVAL 15 MINUTE) ELSE LockedUntil END WHERE UserID=?', 'i', [$user['UserID']]);
            }
            throw new RuntimeException('Invalid email or password.');
        }

        \db_execute('UPDATE USER_ACCOUNT SET FailedLoginCount=0,LockedUntil=NULL,LastLoginAt=NOW() WHERE UserID=?', 'i', [$user['UserID']]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['UserID'];
        return $user;
    }

    private static function validateAccount(string $email,string $password): void
    { if(!\gmail_address($email)) throw new RuntimeException('Use a valid @gmail.com address.'); if(strlen($password)<10) throw new RuntimeException('Password must contain at least 10 characters.'); }
    private static function validateDocument(array $file): array
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('A qualification document is required.');
        $size=(int)$file['size']; if($size>(int)\env('TRAINER_DOCUMENT_MAX_BYTES',5242880)) throw new RuntimeException('Document must be 5 MB or smaller.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
        if(!isset($allowed[$mime])) throw new RuntimeException('Document must be a PDF, JPG, or PNG.');
        return ['mime'=>$mime,'extension'=>$allowed[$mime],'size'=>$size,'original'=>basename((string)$file['name'])];
    }
}
