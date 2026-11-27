import { test } from 'node:test';
import assert from 'node:assert/strict';
import { statusOf } from '../src/app/status.ts';
const now = Date.parse('2026-11-08T10:00:00Z');
test('fresh successful check is operational',()=>{
 assert.equal(statusOf({enabled:true,latest:{available:true,checked_at:'2026-11-08T09:59:00Z'}},now),'operational');
});
test('fresh failed check is down',()=>{
 assert.equal(statusOf({enabled:true,latest:{available:false,checked_at:'2026-11-08T10:00:00+00:00'}},now),'down');
});
test('missing check is pending and disabled service is paused',()=>{
 assert.equal(statusOf({enabled:true,latest:null},now),'pending');
 assert.equal(statusOf({enabled:false,latest:null},now),'paused');
});
test('old, malformed or implausibly future timestamps cannot show operational',()=>{
 for(const checked_at of ['2026-11-08T09:56:59Z','invalid','2026-11-08T10:02:00Z'])
  assert.equal(statusOf({enabled:true,latest:{available:true,checked_at}},now),'stale');
});
