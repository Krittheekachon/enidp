import test from 'node:test';
import assert from 'node:assert/strict';
import { assessmentReviewRoster, isInProgressAssessmentReview, isPendingAssessmentReview } from '../../resources/js/utils/assessmentReviewRoster.js';

test('keeps every assigned reviewer member even before assessment results exist', () => {
    const assignments = [
        { employeeId: 45, employeeName: 'คนแรก', isPending: false, allCompetencies: [] },
        { employeeId: 47, employeeName: 'คนที่สอง', isPending: false, allCompetencies: [] },
    ];
    const roster = assessmentReviewRoster(assignments, [{ db_id: 45, sso: '10', n: 'คนแรก' }]);

    assert.equal(roster.length, 2);
    assert.equal(roster[0].sso, '10');
    assert.equal(roster[1].sso, 'review-47');
    assert.equal(roster[1].n, 'คนที่สอง');
    assert.equal(roster.filter(isPendingAssessmentReview).length, 0);
    assert.equal(roster.filter((person) => !isPendingAssessmentReview(person)).length, 2);
});

test('only a member whose review has reached this step enters the pending list', () => {
    const roster = assessmentReviewRoster([
        { employeeId: 45, isPending: false },
        { employeeId: 47, isPending: true },
    ]);

    assert.deepEqual(roster.filter(isPendingAssessmentReview).map((person) => person.db_id), [47]);
    assert.deepEqual(roster.filter((person) => !isPendingAssessmentReview(person)).map((person) => person.db_id), [45]);
});

test('uses the full assigned competency set when only some results have been saved', () => {
    const saved = [{ competencyId: 1, code: 'CC-001' }];
    const assigned = [
        { id: 1, code: 'CC-001' },
        { id: 2, code: 'FC1-001' },
        { id: 3, code: 'FC1-003' },
    ];
    const [person] = assessmentReviewRoster([
        { employeeId: 47, allCompetencies: saved, assignedCompetencies: assigned },
    ], [
        { db_id: 47, competencyGaps: saved, assignedCompetencies: [{ id: 1, code: 'CC-001' }] },
    ]);

    assert.deepEqual(person.assignedCompetencies.map((row) => row.code), ['CC-001', 'FC1-001', 'FC1-003']);
    assert.deepEqual(person.competencyGaps.map((row) => row.code), ['CC-001']);
});

test('in-progress summary counts only rows with the matching table status', () => {
    const people = [
        { statusMeta: { key: 'in_progress', label: 'อยู่ระหว่างดำเนินการ' } },
        { statusMeta: { label: 'เสร็จสิ้น' }, assessmentAssignment: { isForwarded: true } },
        { statusMeta: { label: 'รอท่านอื่นประเมิน' }, assessmentAssignment: { isForwarded: true } },
    ];

    assert.equal(people.filter(isInProgressAssessmentReview).length, 1);
});
