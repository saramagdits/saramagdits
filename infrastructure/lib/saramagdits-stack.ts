import * as cdk from 'aws-cdk-lib';
import * as s3 from 'aws-cdk-lib/aws-s3';
import * as iam from 'aws-cdk-lib/aws-iam';
import { Construct } from 'constructs';

// TODO: Replace with your actual EC2 instance ID before deploying.
// Find it in the AWS console or via: aws ec2 describe-instances --query 'Reservations[*].Instances[*].InstanceId'
const EC2_INSTANCE_ID = 'i-XXXXXXXXXXXXXXXXX';

export class SaramagditsStack extends cdk.Stack {
  constructor(scope: Construct, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    // -------------------------------------------------------------------------
    // S3 bucket for MySQL backups
    // Lifecycle rule automatically deletes backups older than 30 days.
    // -------------------------------------------------------------------------
    const backupBucket = new s3.Bucket(this, 'DrpalBackups', {
      bucketName: 'saramagdits-db-backups',
      versioned: false,
      removalPolicy: cdk.RemovalPolicy.RETAIN, // Never deleted by CDK destroy
      lifecycleRules: [
        {
          id: 'expire-old-backups',
          expiration: cdk.Duration.days(30),
          enabled: true,
        },
      ],
      // Block all public access — backups should never be public.
      blockPublicAccess: s3.BlockPublicAccess.BLOCK_ALL,
      encryption: s3.BucketEncryption.S3_MANAGED,
    });

    // -------------------------------------------------------------------------
    // IAM role for the EC2 instance
    // Grants write access to the backup bucket and SSM for remote management.
    // -------------------------------------------------------------------------
    const ec2Role = new iam.Role(this, 'Ec2InstanceRole', {
      roleName: 'saramagdits-ec2-role',
      assumedBy: new iam.ServicePrincipal('ec2.amazonaws.com'),
      description: 'Role for saramagdits.com EC2 instance - S3 backup writes + SSM',
    });

    // Allow the EC2 instance to write backups to S3.
    backupBucket.grantWrite(ec2Role);

    // SSM Session Manager — enables browser-based SSH without opening port 22.
    // Optional but recommended: lets you connect via AWS console if SSH is lost.
    ec2Role.addManagedPolicy(
      iam.ManagedPolicy.fromAwsManagedPolicyName('AmazonSSMManagedInstanceCore'),
    );

    // -------------------------------------------------------------------------
    // Instance profile — wraps the IAM role for attachment to an EC2 instance.
    //
    // After CDK deploy, attach to the existing EC2 via AWS console:
    //   EC2 → Instances → select instance → Actions → Security → Modify IAM role
    //
    // Or via CLI (replace PROFILE_NAME with the output from `cdk deploy`):
    //   aws ec2 associate-iam-instance-profile \
    //     --instance-id i-XXXXXXXXXXXXXXXXX \
    //     --iam-instance-profile Name=<PROFILE_NAME>
    // -------------------------------------------------------------------------
    const instanceProfile = new iam.CfnInstanceProfile(this, 'Ec2InstanceProfile', {
      instanceProfileName: 'saramagdits-ec2-instance-profile',
      roles: [ec2Role.roleName],
    });

    // -------------------------------------------------------------------------
    // Outputs — printed after `cdk deploy` completes.
    // -------------------------------------------------------------------------
    new cdk.CfnOutput(this, 'BackupBucketName', {
      value: backupBucket.bucketName,
      description: 'S3 bucket name for MySQL backups',
    });

    new cdk.CfnOutput(this, 'InstanceProfileName', {
      value: instanceProfile.instanceProfileName ?? 'saramagdits-ec2-instance-profile',
      description: 'Attach this instance profile to your existing EC2 instance',
    });

    new cdk.CfnOutput(this, 'Ec2RoleName', {
      value: ec2Role.roleName,
      description: 'IAM role name for EC2 instance',
    });

    new cdk.CfnOutput(this, 'AttachProfileCommand', {
      value: `aws ec2 associate-iam-instance-profile --instance-id ${EC2_INSTANCE_ID} --iam-instance-profile Name=saramagdits-ec2-instance-profile`,
      description: 'Run this command to attach the instance profile to your EC2 instance',
    });
  }
}
