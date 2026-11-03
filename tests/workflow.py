"""HTTP integration tests against an isolated *_test database seeded by seed.php.
Standard library only. Set SK_TEST_PASSWORD; server must use the same test DB.
"""
import os,re,json,io,uuid,urllib.request,urllib.parse,http.cookiejar,base64
BASE=os.environ.get('SK_TEST_URL','http://127.0.0.1:8080/index.php')
PASSWORD=os.environ['SK_TEST_PASSWORD']
count=0

def check(condition,label):
 global count
 assert condition,label
 count+=1
 print('PASS',label)

class Client:
 def __init__(self):
  self.jar=http.cookiejar.CookieJar()
  self.opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(self.jar))
 def get(self,page='dashboard',**args):
  try:
   with self.opener.open(BASE+'?'+urllib.parse.urlencode(dict(page=page,**args))) as r:return r.status,r.read().decode(errors='replace'),r.url
  except urllib.error.HTTPError as r:return r.code,r.read().decode(errors='replace'),r.url
 def post(self,action,page='dashboard',files=None,**data):
  _,html,_=self.get('login' if action=='login' else page)
  token=re.search(r'name="csrf" value="([a-f0-9]+)"',html)
  if 'csrf' not in data:data['csrf']=token.group(1) if token else ''
  data['action']=action
  if files:
   boundary=uuid.uuid4().hex;parts=[]
   for key,value in data.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
   for key,(filename,content,mime) in files.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+content+b'\r\n')
   body=b''.join(parts)+f'--{boundary}--\r\n'.encode();ctype='multipart/form-data; boundary='+boundary
  else:body=urllib.parse.urlencode(data).encode();ctype='application/x-www-form-urlencoded'
  request=urllib.request.Request(BASE+'?page='+page,data=body,headers={'Content-Type':ctype})
  try:
   with self.opener.open(request) as r:return r.status,r.read().decode(errors='replace'),r.url
  except urllib.error.HTTPError as r:return r.code,r.read().decode(errors='replace'),r.url
 def login(self,role):
  result=self.post('login',page='login',email=role+'@example.test',password=PASSWORD)
  check(result[0]==200 and 'Invalid email' not in result[1] and 'User Login' not in result[1],role+' login')
  return self

def ok(result,text,label):check(result[0]==200 and text in result[1],label)
def find_id(html,title):
 # IDs used in generated project detail links, in the same table row as the title.
 for row in re.findall(r'<tr>.*?</tr>',html,re.S):
  if title in row:
   m=re.search(r'(?:id|edit)=(\d+)',row)
   if m:return int(m.group(1))
 raise AssertionError('Cannot find project '+title)

a=Client().login('admin');c=Client().login('chairperson');t=Client().login('treasurer');o=Client().login('official');r=Client().login('resident');public=Client()
for cl,pages in [(a,['dashboard','users','settings','audit','reports','profile']),(c,['dashboard','profile','proposals','monitoring','approvals','reports']),(t,['dashboard','profile','allocation','expenses','balance','transactions']),(o,['dashboard','profile','projects','summary']),(public,['welcome','login','dashboard','projects','summary'])]:
 for page in pages:
  status,html,_=cl.get(page)
  check(status==200 and 'needs setup' not in html and 'Warning:' not in html and 'Fatal error' not in html,'render '+page)
check(o.get('users')[0]==403,'official cannot view account management')
check(public.get('allocation')[0]==403,'public cannot view allocation')
ok(o.post('save_setting',page='projects',type='categories',name='Forbidden',description=''), 'permission','official cannot mutate settings')
ok(a.post('save_setting',page='settings',type='categories',name='Education',description='Test category'),'Settings updated','category CRUD create')
ok(a.post('save_setting',page='settings',type='fund_sources',name='Youth Fund',description='Test source'),'Settings updated','fund source create')
ok(a.post('save_setting',page='settings',id=1,type='categories',name='Education',description='Updated'),'Settings updated','category update')
ok(a.post('save_role',page='users',name='Committee Member',description='Official permissions',code='official'),'Role saved','custom role permission template')
ok(a.post('save_user',page='users',name='New Member',email='member@example.test',role_id=4,active=1,temporary_password=PASSWORD),'Account saved','account creation')
member=Client();member.login('member')
# login helper expects account name @example.test, same generated password
check('page=profile' in member.get()[2],'temporary password forces change')
ok(member.post('password',page='profile',current_password=PASSWORD,new_password=PASSWORD+'-changed',confirm_password=PASSWORD+'-changed'),'Password updated','temporary password activation')
project_data=dict(category_id=1,source_id=1,title='Youth Learning Project',description='Community learning activity',location='Barangay Hall',committee='Education Committee',beneficiaries='Youth residents',beneficiary_count=20,start_date='2026-01-01',end_date='2026-12-31',planned_amount='1000.00')
ok(o.post('save_project',page='projects',**project_data),'submitted for Chairperson review','proposal creation')
html=c.get('proposals')[1];pid=find_id(html,project_data['title'])
check(project_data['title'] not in public.get('projects')[1],'pending proposal hidden from public')
ok(member.post('progress',page='projects',project_id=pid,status='Ongoing',progress=20,details='Unauthorized'),'belongs to another official','project ownership enforced')
ok(c.post('review_project',page='proposals',id=pid,decision='Approve',remarks='Reviewed'),'Project review saved','proposal review approval')
check(project_data['title'] not in public.get('projects')[1],'proposal approval does not auto-publish')
ok(c.post('review_project',page='proposals',id=pid,decision='Publish',remarks='Public information approved'),'Project review saved','separate publication approval')
check(project_data['title'] in public.get('projects')[1],'approved publication visible publicly')
ok(t.post('fund_entry',page='allocation',source_id=1,amount='1000.00',entry_date='2026-01-01',reference='TEST-AUTH-1'),'Funding recorded','funding receipt')

def request(kind,amount,expected='Request sent',**extra):
 result=t.post('fund_request',page='allocation',project_id=pid,type=kind,amount=amount,reason='Testing '+kind,**extra)
 ok(result,expected,kind+' request')
 html=c.get('approvals')[1]
 ids=re.findall(r'name="id" value="(\d+)"',html)
 return int(ids[0])
def decide(rid,expected='Request approved.'):
 result=c.post('decide_request',page='approvals',id=rid,decision='Approved',decision_notes='Reviewed')
 ok(result,expected,'financial decision '+str(rid))

rid=request('Allocation','1000.00');decide(rid)
ok(c.post('decide_request',page='approvals',id=rid,decision='Approved',decision_notes='Again'),'no longer pending','duplicate approval prevented')
rid=request('Allocation','1.00');decide(rid,'Insufficient unallocated funds')
rid=request('Disbursement','500.00');decide(rid)
rid=request('Disbursement','501.00');decide(rid,'exceeds the unreleased')
png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jN1kAAAAASUVORK5CYII=')
expense=dict(project_id=pid,amount='125.50',expense_date='2026-01-02',description='Learning materials',payee='Test Supplier',receipt_date='2026-01-02',receipt_reference='TEST-R-1')
ok(t.post('expense',page='expenses',files={'receipt':('receipt.png',png,'image/png')},**expense),'Expense and receipt saved','expense and receipt upload')
check('₱ 874.50' in t.get('balance')[1],'exact remaining budget calculation')
check('₱ 125.50' in public.get('summary')[1],'public summary uses database expenses')
html=t.get('transactions')[1];eid=int(re.search(r'kind=receipt&amp;id=(\d+)',html).group(1))
check(public.get('file',kind='receipt',id=eid)[0]==403,'receipt authorization blocks public access')
check(o.get('file',kind='receipt',id=eid)[0]==403,'receipt authorization blocks official access')
check(t.get('file',kind='receipt',id=eid)[0]==200,'treasurer can retrieve receipt')
expense['amount']='501.00'
ok(t.post('expense',page='expenses',files={'receipt':('receipt.png',png,'image/png')},**expense),'exceeds available released funds','overspending prevented')
expense['amount']='1.00'
ok(t.post('expense',page='expenses',files={'receipt':('payload.php',b'<?php echo 1;?>','image/png')},**expense),'Use JPEG','fake image rejected')
expense['amount']='100.00'
ok(t.post('expense',page='expenses',id=eid,**expense),'Expense and receipt saved','expense update')
check('₱ 900.00' in t.get('balance')[1],'expense update recalculates balance')
ok(o.post('progress',page='projects',project_id=pid,status='Ongoing',progress=40,details='Materials purchased'),'Progress recorded','progress update')
check(project_data['title'] not in public.get('projects')[1],'progress update waits for publication review')
ok(c.post('review_project',page='proposals',id=pid,decision='Publish',remarks='Update reviewed'),'Project review saved','republish reviewed update')
# Realignment: create and approve a second project, transfer only unreleased funds.
project_data['title']='Youth Skills Project'
ok(o.post('save_project',page='projects',**project_data),'submitted for Chairperson review','second proposal creation')
pid2=find_id(c.get('proposals')[1],project_data['title'])
c.post('review_project',page='proposals',id=pid2,decision='Approve',remarks='Reviewed')
result=t.post('fund_request',page='allocation',project_id=pid2,type='Realignment',from_project_id=pid,amount='200.00',reason='Reallocate unused budget')
ok(result,'Request sent','realignment request')
ids=re.findall(r'name="id" value="(\d+)"',c.get('approvals')[1]);decide(int(ids[0]))
check('₱ 700.00' in t.get('balance')[1] and '₱ 200.00' in t.get('balance')[1],'realignment updates both projects')
ok(a.post('save_setting',page='settings',type='categories',name='Invalid CSRF',description='',csrf='bad'),'form expired','CSRF rejection')
ok(a.post('save_user',page='users',id=1,role_id=1,name='Admin Test',email='admin@example.test'),'Keep at least one active Admin','last administrator protection')
ok(c.post('review_project',page='proposals',id=pid,decision='Publish',remarks='<script>alert(1)</script>'),'Project review saved','review remarks save')
check('&lt;script&gt;' in c.get('proposals',id=pid)[1] and '<script>alert(1)</script>' not in c.get('proposals',id=pid)[1],'stored text is HTML escaped')
ok(a.post('reset_password',page='users',id=4,temporary_password=PASSWORD+'reset'),'Temporary password set','admin password reset')
check(o.get('profile')[0]==403,'password reset invalidates existing sessions')
check('Approved' in a.get('audit')[1] and 'Save expense' in a.get('audit')[1],'audit records include financial decisions')
for cl in (a,c,t,member,r):
 for page in ('dashboard','profile'):
  status,html,_=cl.get(page)
  check(status==200 and 'Fatal error' not in html and 'Warning:' not in html,'post-workflow '+page)
print(f'{count} integration checks passed')
