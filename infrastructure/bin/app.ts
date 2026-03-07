#!/usr/bin/env node
import 'source-map-support/register';
import * as cdk from 'aws-cdk-lib';
import { SaramagditsStack } from '../lib/saramagdits-stack';

const app = new cdk.App();

new SaramagditsStack(app, 'SaramagditsStack', {
  env: {
    account: process.env.CDK_DEFAULT_ACCOUNT,
    region: 'us-east-1',
  },
  description: 'Supporting infrastructure for saramagdits.com Drupal 10 site',
});
