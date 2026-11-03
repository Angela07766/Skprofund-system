"""Run after workflow.py/intelligent_feedback.py on the disposable test database."""
import os,shutil
from pathlib import Path
from playwright.sync_api import sync_playwright
url=os.environ.get('SK_TEST_URL','http://127.0.0.1:8080/index.php')
password=os.environ['SK_TEST_PASSWORD'];out=Path('tests/artifacts');out.mkdir(exist_ok=True)
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True,executable_path=os.environ.get('SK_CHROMIUM') or shutil.which('chromium'),args=['--no-sandbox'])
 context=browser.new_context(viewport={'width':1366,'height':1000});page=context.new_page();errors=[]
 page.on('pageerror',lambda error: errors.append(str(error)))
 page.goto(url+'?page=login');page.get_by_label('Email address').fill('member@example.test');page.get_by_label('Password',exact=True).fill(password+'-changed');page.get_by_role('button',name='Login',exact=True).click();page.wait_for_url('**page=dashboard')
 page.goto(url+'?page=projects');generator=page.locator('#project-generator')
 generator.get_by_label('Youth need / problem to address').fill('Students need reading tutoring and mathematics practice.')
 generator.get_by_label('Category',exact=True).select_option('1');generator.get_by_label('Fund Source',exact=True).select_option('1');generator.get_by_label('Budget Ceiling (PHP)').fill('1000');generator.get_by_label('Proposed Beneficiary Count').fill('20');generator.get_by_label('Target Beneficiaries',exact=True).fill('Youth students')
 generator.get_by_role('button',name='Generate Suggested Proposals',exact=True).click();page.wait_for_url('**suggestions=1')
 assert page.get_by_role('heading',name='Recommended Proposals',exact=True).is_visible()
 assert page.locator('body').evaluate('(el)=>el.scrollWidth<=innerWidth')
 generator=page.locator('#project-generator');generator.screenshot(path=str(out/'project-generator-desktop.png'))
 generator.get_by_role('button',name='Use as Draft',exact=True).first.click();page.wait_for_url('**generated=1')
 assert page.get_by_role('heading',name='Generated Proposal Draft',exact=True).is_visible()
 assert page.get_by_label('Project Title',exact=True).input_value()=='Youth Learning Support Sessions'
 assert page.get_by_label('Start Date',exact=True).input_value()==''
 page.get_by_label('Project Title',exact=True).fill('Human-edited learning proposal')
 assert page.get_by_label('Project Title',exact=True).input_value()=='Human-edited learning proposal'
 phone=browser.new_context(viewport={'width':390,'height':844},is_mobile=True,has_touch=True).new_page()
 phone.goto(url+'?page=feedback');assert phone.get_by_role('button',name='Send to SK Team',exact=True).is_visible()
 assert phone.locator('body').evaluate('(el)=>el.scrollWidth<=innerWidth')
 assert 'resident comment' not in phone.locator('body').inner_text()
 phone.screenshot(path=str(out/'feedback-mobile.png'),full_page=True)
 admin=browser.new_context().new_page();admin.goto(url+'?page=login');admin.get_by_label('Email address').fill('admin@example.test');admin.get_by_label('Password',exact=True).fill(password);admin.get_by_role('button',name='Login',exact=True).click();admin.wait_for_url('**page=dashboard')
 admin.get_by_role('link',name='Community Feedback',exact=True).click();assert admin.get_by_role('heading',name='Community Feedback Inbox',exact=True).is_visible()
 admin.get_by_text('Read / Review',exact=True).first.click();assert admin.get_by_label('Review Status',exact=True).first.is_visible()
 assert not errors,errors
 browser.close()
print('Browser checks passed: ranked generation, editable unsaved draft, mobile public feedback privacy/layout, staff inbox navigation; no JavaScript runtime errors.')
