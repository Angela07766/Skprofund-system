"""Run after workflow.py on the same disposable test DB. Standard library only."""
import os,re,runpy
# Load just the HTTP helpers, without executing workflow.py's test body.
source=open('tests/workflow.py').read().split("a=Client().login('admin')",1)[0]
exec(compile(source,'tests/workflow.py','exec'))
admin=Client().login('admin');chair=Client().login('chairperson');guest=Client();resident=Client().login('resident')
official=Client()
ok(official.post('login',page='login',email='member@example.test',password=PASSWORD+'-changed'),'Dashboard','official-template account login')
# member's password was changed by workflow.py.

criteria=dict(need='Students need reading tutoring and mathematics practice.',budget_ceiling='1000.00',category_id=1,source_id=1,beneficiaries='Youth students',beneficiary_count=20)
ok(official.post('generate_ideas',page='projects',**criteria),'Youth Learning Support Sessions','generate ranked project ideas')
html=official.get('projects')[1]
check('Planning ceiling only' in html and 'not a cost estimate' in html,'cold-start generation explains unknown costs')
check('exceeds current unallocated' in html,'generator warns when source funds are insufficient')
check('rule-based' in html and 'not AI or machine learning' in html,'algorithm honestly described')
key=re.search(r'name="suggestion_key" value="([^"]+)"',html).group(1)
before=chair.get('proposals')[1].count('<tr>')
result=official.post('use_suggestion',page='projects',suggestion_key=key)
ok(result,'Generated Proposal Draft','generate editable draft')
check('name="planned_amount" value="1000.00"' in result[1],'planning amount prefilled without reserving funds')
check('name="start_date" value=""' in result[1] and 'name="location" value=""' in result[1],'schedule and location require human input')
check(chair.get('proposals')[1].count('<tr>')==before,'draft generation creates no project records')
ok(official.post('use_suggestion',page='projects',suggestion_key='template:forged'),'current results','forged suggestion key rejected')
ok(guest.post('generate_ideas',page='feedback',**criteria),'Please log in','guest cannot generate proposals')
ok(resident.post('generate_ideas',page='feedback',**criteria),'permission','resident cannot generate staff proposals')
criteria['need']='completely unrelated astronomy telescope'
ok(official.post('generate_ideas',page='projects',**criteria),'No suitable matches','no unsupported recommendation fabricated')
# Submit the previously generated, human-completed draft through the existing approval flow.
new=dict(category_id=1,source_id=1,title='Generated Learning Draft',description='Reviewed draft objective and activities',location='Barangay Hall',committee='Education Committee',beneficiaries='Youth students',beneficiary_count=20,start_date='2027-01-01',end_date='2027-02-01',planned_amount='1000.00')
ok(official.post('save_project',page='projects',**new),'submitted for Chairperson review','human-reviewed generated proposal uses normal approval flow')
check('Generated Learning Draft' not in guest.get('projects')[1],'generated proposal is private pending approval')
message=dict(feedback_type='Suggestion',project_id='',display_name='Youth Visitor',subject='Reading sessions',message='Please consider reading tutoring sessions for students.')
ok(guest.post('submit_feedback',page='feedback',**message),'sent privately','guest submits project suggestion')
message['feedback_type']='Comment';message['subject']='Project feedback';message['message']='<script>alert(1)</script> This is a resident comment.';message['project_id']=1
ok(resident.post('submit_feedback',page='feedback',**message),'sent privately','resident comments on published project')
html=admin.get('feedback')[1]
check('Reading sessions' in html and 'Project feedback' in html,'admin inbox receives both types')
check('&lt;script&gt;' in html and '<script>alert(1)</script>' not in html,'feedback text is safely escaped')
check('resident comment' not in guest.get('feedback')[1] and 'Reading sessions' not in guest.get('feedback')[1],'submitted messages not publicly listed')
check(official.get('feedback')[0]==403,'official cannot access private inbox')
check('Reading sessions' in chair.get('feedback')[1],'chairperson can read community feedback')
id=int(re.search(r'name="id" value="(\d+)"',html).group(1))
ok(chair.post('review_feedback',page='feedback',id=id,status='Reviewed',review_notes='Consider during planning'),'Feedback review saved','chairperson moderates feedback')
check('Consider during planning' in admin.get('feedback',status='Reviewed')[1],'review notes and status retained')
ok(resident.post('review_feedback',page='feedback',id=id,status='Archived',review_notes='Not authorized'),'permission','resident cannot moderate messages')
message['project_id']=2
ok(guest.post('submit_feedback',page='feedback',**message),'currently public','feedback cannot target private project')
message['project_id']=''
for i in range(3):
 message['subject']='Suggestion '+str(i)
 ok(guest.post('submit_feedback',page='feedback',**message),'sent privately','allowed message rate '+str(i))
ok(guest.post('submit_feedback',page='feedback',**message),'limit reached','persistent per-connection rate limit')
check('Community Feedback' in admin.get('audit')[1],'moderation creates audit event')
print(f'{count} generator and feedback HTTP checks passed')
