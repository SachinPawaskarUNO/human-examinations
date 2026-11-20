import assert from 'node:assert/strict';
import {test} from 'node:test';
import {createExaminationDocument, parseExaminationDocument, examinationCsv} from '../../resources/js/examinationTransfer.js';

const rows=[{id:91,number:7,type:'Scar',label:'Finding',region:'Custom region',length_mm:'32.50',width_mm:'0.00',sketch_id:'body-front',x:'0.000000',y:'1.000000'},{id:92,number:22,type:'Tattoo',label:'Unmapped'}];
const exam={id:31,reference:'EX-TEST',examiner:'Demo Examiner',date:'2026-11-19',notes:'Example notes',created_at:'not portable'};

test('portable documents round-trip editable data without database identities',()=>{
 const data=createExaminationDocument(exam,rows);
 assert.equal(data.format,'spawaskar/human-examinations');assert.equal(data.version,1);
 assert.equal(data.examination.reference,'EX-TEST');assert.equal('id' in data.examination,false);
 assert.equal('id' in data.observations[0],false);assert.deepEqual(data.observations.map(o=>o.number),[7,22]);
 assert.equal(data.observations[0].length_mm,'32.50');assert.equal(data.observations[0].x,'0.000000');assert.equal(data.observations[1].sketch_id,null);
 assert.deepEqual(parseExaminationDocument(JSON.parse(JSON.stringify(data)),{types:['Scar','Tattoo']}),data);
 assert.equal(createExaminationDocument({},[]).observations.length,0);
});

test('unknown formats, versions, duplicate numbers, and unsafe identities are rejected',()=>{
 const data=createExaminationDocument(exam,rows);
 for(const changed of [{...data,format:'other'},{...data,version:2},{...data,version:'1'},{...data,id:7},{...data,examination:{...data.examination,id:8}},{...data,observations:[data.observations[0],data.observations[0]]},{...data,observations:[{...data.observations[0],id:4}]}])assert.throws(()=>parseExaminationDocument(changed));
 assert.throws(()=>parseExaminationDocument(data,{types:['Scar']}),/type/i);
 assert.throws(()=>parseExaminationDocument(data,{maxObservations:1}),/observations/i);
});

test('invalid fields and incomplete locations fail before replacing local state',()=>{
 const data=createExaminationDocument(exam,rows);
 for(const change of [{number:0},{number:true},{number:'7'},{length_mm:-1},{width_mm:Infinity},{region:'x'.repeat(256)},{sketch_id:null},{x:1.1},{y:null},{sketch_id:''},{type:''}])assert.throws(()=>parseExaminationDocument({...data,observations:[{...data.observations[0],...change}]}));
 assert.throws(()=>parseExaminationDocument({...data,examination:{...data.examination,date:'2026-02-31'}}),/date/i);
 const custom={...data.observations[0],sketch_id:'host-custom-sketch'};
 assert.equal(parseExaminationDocument({...data,observations:[custom]}).observations[0].sketch_id,'host-custom-sketch');
});

test('decimal fields reject JavaScript-only number string syntax', () => {
    const data = createExaminationDocument(exam, rows);
    for (const value of ['0x1', '0b1', '0o1']) {
        assert.throws(() => parseExaminationDocument({
            ...data,
            observations: [{...data.observations[0], x: value}],
        }), /x coordinate/);
    }
});

test('CSV retains metadata, quotes line breaks, and neutralizes spreadsheet formulas',()=>{
 const data=createExaminationDocument({...exam,reference:'=1+1',notes:'A "quoted", note\nSecond line'},[{...rows[0],label:'@SUM(1,2)'}]);
 const csv=examinationCsv(data);
 assert.ok(csv.startsWith('\uFEFF'));assert.match(csv,/examination_reference/);
 assert.ok(csv.includes('"\'=1+1"'));assert.ok(csv.includes('"\'@SUM(1,2)"'));
 assert.ok(csv.includes('"A ""quoted"", note\nSecond line"'));assert.ok(examinationCsv(createExaminationDocument({},[])).split('\r\n').length>=2);
});
