"""Browser smoke checks for the disposable integration-test server.
Requires Playwright + Chromium and SK_TEST_PASSWORD. Run after workflow.py.
"""
import os,shutil
from pathlib import Path
from playwright.sync_api import sync_playwright
url=os.environ.get('SK_TEST_URL','http://127.0.0.1:8080/index.php')
password=os.environ['SK_TEST_PASSWORD']
out=Path('tests/artifacts');out.mkdir(exist_ok=True)
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True,executable_path=os.environ.get('SK_CHROMIUM') or shutil.which('chromium'),args=['--no-sandbox'])
 page=browser.new_page(viewport={'width':1440,'height':1000})
 errors=[]
 page.on('pageerror',lambda error: errors.append(str(error)))
 page.goto(url+'?page=login');page.get_by_label('Email address').fill('admin@example.test');page.get_by_label('Password',exact=True).fill(password);page.get_by_role('button',name='Login',exact=True).click();page.wait_for_url('**page=dashboard')
 for key in ['dashboard','users','settings','audit','reports','profile']:
  page.goto(url+'?page='+key);page.locator('footer').wait_for()
  assert page.locator('h1').count()==1,key
  assert page.locator('body').evaluate('(el)=>el.scrollWidth<=innerWidth'),key+' horizontal overflow'
  if key in ['dashboard','reports']:page.screenshot(path=str(out/(key+'-desktop.png')),full_page=True)
 # Follow Add User/Role and Settings links to exercise forms not shown in recordings.
 page.goto(url+'?page=users');page.get_by_role('link',name='+ Add User').click();assert page.get_by_label('Temporary Password',exact=True).is_visible()
 page.goto(url+'?page=users&tab=roles');page.get_by_role('link',name='+ Add Role').click();assert page.get_by_label('Permission Template').is_visible()
 page.goto(url+'?page=settings');page.get_by_role('link',name='+ Add Category',exact=True).click();assert page.get_by_label('Name',exact=True).is_visible()
 # Native confirmation dialog cancels without deleting a category.
 page.goto(url+'?page=settings');page.get_by_role('button',name='Delete',exact=True).first.click();assert page.get_by_role('dialog').is_visible();page.get_by_role('button',name='Cancel',exact=True).click();assert not page.get_by_role('dialog').is_visible()
 # Exercise role-specific pages and project/expense forms in a real browser.
 for role,secret,pages in [('chairperson',password,['dashboard','proposals&id=1','monitoring&id=1','approvals','reports']),('treasurer',password,['dashboard','allocation','expenses&edit=1','balance','transactions']),('member',password+'-changed',['dashboard','projects&edit=new','summary'])]:
  context=browser.new_context(viewport={'width':1280,'height':900});view=context.new_page()
  view.on('pageerror',lambda error: errors.append(str(error)))
  view.goto(url+'?page=login');view.get_by_label('Email address').fill(role+'@example.test');view.get_by_label('Password',exact=True).fill(secret);view.get_by_role('button',name='Login',exact=True).click();view.wait_for_url('**page=dashboard')
  for key in pages:
   response=view.goto(url+'?page='+key);assert response.status==200,key
   assert 'needs setup' not in view.locator('body').inner_text(),key
   assert view.locator('body').evaluate('(el)=>el.scrollWidth<=innerWidth'),key+' overflow'
  context.close()
 phone=browser.new_context(viewport={'width':390,'height':844},is_mobile=True,has_touch=True).new_page()
 phone.goto(url+'?page=dashboard');assert phone.locator('body').evaluate('(el)=>el.scrollWidth<=innerWidth')
 phone.get_by_role('button',name='Toggle navigation').click();assert phone.get_by_role('button',name='Toggle navigation').get_attribute('aria-expanded')=='true'
 phone.get_by_role('link',name='View Projects',exact=True).click();assert phone.get_by_role('heading',name='View Projects',exact=True).is_visible()
 phone.screenshot(path=str(out/'public-projects-mobile.png'),full_page=True)
 phone.goto(url+'?page=welcome');assert phone.locator('body').evaluate('(el)=>el.scrollWidth<=innerWidth');phone.screenshot(path=str(out/'welcome-mobile.png'),full_page=True)
 assert not errors,errors
 browser.close()
print('Browser checks passed: desktop role pages, missing forms, confirmation cancel, mobile navigation, responsive overflow, no JavaScript runtime errors.')
