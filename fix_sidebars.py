import os

# Run this script from inside your AURORA-Platform folder
# cd ~/Desktop/Projects/AURORA/AURORA-Platform
# python3 fix_sidebars.py

pages = [
    'dashboard.html',
    'sales.html',
    'inventory.html',
    'reviews.html',
    'strategies.html',
    'forecast.html',
    'owner-portal.html',
    'reports.html',
    'manage.html',
]

insert_block = '''    <div class="sidebar-section">
      <div class="sidebar-label">Data</div>
      <a href="manage.html" class="sidebar-item"><span class="sidebar-icon">✏️</span> Manage Data</a>
    </div>
'''

for page in pages:
    if not os.path.exists(page):
        print(f'⚠️  Skipped (not found): {page}')
        continue

    with open(page, 'r') as f:
        content = f.read()

    if 'Manage Data' in content:
        print(f'✅ Already has Manage Data: {page}')
        continue

    if '<div class="sidebar-footer">' not in content:
        print(f'⚠️  No sidebar-footer found: {page}')
        continue

    content = content.replace(
        '    <div class="sidebar-footer">',
        insert_block + '    <div class="sidebar-footer">',
        1  # only replace first occurrence
    )

    with open(page, 'w') as f:
        f.write(content)

    print(f'✅ Fixed: {page}')

print('\nDone! Now run:')
print('cp -r ~/Desktop/Projects/AURORA/AURORA-Platform/* /Applications/XAMPP/xamppfiles/htdocs/AURORA/')