import test from 'node:test';
import assert from 'node:assert/strict';
import { availableAssessmentHubTab, initialAssessmentHubTab } from '../../resources/js/utils/assessmentHubTab.js';

test('later assessment reviewers start on results but can open the FC topics tab', () => {
    assert.equal(initialAssessmentHubTab(false), 'results');
    assert.equal(availableAssessmentHubTab('topics', true), 'topics');
});

test('reviewers without result approval access stay on FC topics', () => {
    assert.equal(initialAssessmentHubTab(true), 'topics');
    assert.equal(availableAssessmentHubTab('results', false), 'topics');
});
