# Fashion Creator Challenge - Testing Guide

## 🧪 How to Test the Feature

### Step 1: Start the Application
```bash
cd c:\Users\Barbie\VeraBelle-FINALS-main
php bin/console server:run  # or: symfony server:start
```

### Step 2: Test User Routes

#### View Main Challenge Page
```
URL: http://localhost:8000/challenge
Expected: See challenge listing page with current, upcoming, and past challenges
```

#### View Sample Challenge
```
URL: http://localhost:8000/challenge/1
Expected: See challenge details with submission gallery (empty initially)
```

#### Submit Entry (Login Required)
```
1. Go to http://localhost:8000/challenge/1/submit
2. If not logged in → Redirect to login
3. After login → Fill out form:
   - Choose entry type (Outfit or Design)
   - Add title
   - Add description  
   - Upload images
   - Click Submit
```

#### Vote on Entry (AJAX)
```
1. Go to http://localhost:8000/challenge/1
2. Click "Vote" button on an entry
3. Expected: Vote count increases instantly, no page reload
4. Click again: Vote toggles off
```

#### View Creator Profile
```
URL: http://localhost:8000/challenge/creator/username
Expected: See creator's entries, badges, and vote stats
```

#### View Hall of Fame
```
URL: http://localhost:8000/challenge/hall-of-fame
Expected: See gallery of top-voted entries from past challenges
```

### Step 3: Test Admin Routes

#### Access Admin Panel (Login as Admin Required)
```
URL: http://localhost:8000/challenge/admin/list
Expected: List of all challenges with management options
```

#### Create New Challenge
```
1. Go to http://localhost:8000/challenge/admin/create
2. Fill in form:
   - Title: "Summer Collection Challenge"
   - Description: Challenge description
   - Theme: Optional theme
   - Start Date: Tomorrow
   - End Date: 2 weeks from now
   - Voting Start: 2 days before end
   - Voting End: End date
3. Submit
Expected: Challenge created and listed
```

#### Edit Challenge
```
1. Go to http://localhost:8000/challenge/admin/list
2. Click "Edit" on any challenge
3. Modify fields
4. Save
Expected: Changes applied
```

#### Manage Entries for Challenge
```
1. Go to http://localhost:8000/challenge/admin/list
2. Click "Manage Entries" on a challenge
Expected: See all submissions for that challenge
```

#### Finalize Challenge & Award Badges
```
1. Go to http://localhost:8000/challenge/admin/list
2. Click "Finalize" on a closed challenge
Expected: Top 3 entries ranked, badges awarded to creators
```

### Step 4: Test Database Operations

#### Check Entities Loaded
```bash
php bin/console doctrine:schema:validate
Expected: [OK] The database schema is in sync with the mapping files.
```

#### View Challenges in Database
```bash
php bin/console doctrine:query:sql "SELECT id, title, status FROM challenge"
```

#### View Entries
```bash
php bin/console doctrine:query:sql "SELECT id, title, vote_count FROM entry"
```

#### View Votes
```bash
php bin/console doctrine:query:sql "SELECT COUNT(*) as total_votes FROM vote"
```

#### View BadgesPhp
```bash
php bin/console doctrine:query:sql "SELECT user_id, badge_name FROM user_badge"
```

### Step 5: Complete User Journey

#### Scenario 1: User Submits and Creates Content
```
1. Login to user account
2. Go to /challenge
3. Click on active challenge
4. Submit outfit entry with photos
5. View in gallery
6. Vote on other entries
7. Check /challenge/creator/yourname
8. See entry in profile
```

#### Scenario 2: Admin Creates & Manages Challenge
```
1. Login as admin
2. Go to /challenge/admin/list
3. Create new challenge with dates
4. Share link to users
5. Monitor /challenge/admin/{id}/entries
6. When voting ends, click Finalize
7. Check that top 3 have badges
```

#### Scenario 3: Community Voting
```
1. Multiple users log in
2. Each views /challenge/{id}
3. Each votes on entries
4. Vote counts update in real-time
5. After voting period, highest votes = winners
6. Check /challenge/hall-of-fame for winners
```

### Step 6: Browser DevTools Tests

#### Test AJAX Voting (Open DevTools)
```
1. Go to Chrome DevTools → Network tab
2. Go to /challenge/{id}
3. Click a Vote button
4. Watch Network tab: See POST to /challenge/{id}/vote
5. Response shows: {success: true, voteCount: X}
```

#### Check LocalStorage (if implemented in future)
```
1. Go to Chrome DevTools → Application → LocalStorage
2. Submit entries/votes
3. Data persists across page reloads
```

### Step 7: Test Error Handling

#### Submit Without Login
```
Expected: Redirect to /login
```

#### Vote Without Login
```
Expected: Show login prompt or redirect
```

#### Access Admin Routes as Non-Admin
```
Expected: Access denied (403 Forbidden)
```

#### Upload Invalid File Format
```
Expected: Error message and form remains filled
```

## 📱 Test Checklist

✅ Users can view challenges
✅ Users can view entries and vote counts
✅ Users can submit entries (with login)
✅ Vote button toggles on/off
✅ Vote counts update instantly (no reload)
✅ Creator profiles display badges
✅ Hall of Fame shows top entries
✅ Admins can create challenges
✅ Admins can edit challenges
✅ Admins can view all entries
✅ Admins can finalize and award badges
✅ Database properly persists data
✅ Foreign keys maintain referential integrity
✅ Unique constraint prevents duplicate votes
✅ CSRF protection active
✅ Authentication guards protected routes

## 🐛 Debugging Tips

### If Routes Don't Work
```bash
php bin/console debug:router | grep challenge
# Should show all challenge routes
```

### If Entities Not Found
```bash
php bin/console doctrine:schema:validate
# Should show [OK] if schema is in sync
```

### If Forms Don't Submit
```bash
# Check Symfony logs
tail -f var/log/dev.log

# Check database connection
php bin/console doctrine:query:sql "SELECT 1"
```

### If Voting Doesn't Work
```bash
# Check if user is authenticated
# Check browser console for JavaScript errors
# Check Network tab for failed requests
```

### If File Uploads Fail
```bash
# Check public/uploads/entries/ exists
# Check permissions: 755
# Check file size limits in php.ini
```

## 📊 Expected Database State After Testing

After running through all tests, your database should have:
- ✅ 1-2 Challenge records
- ✅ 3-5 Entry records
- ✅ 5-10 Vote records
- ✅ 0-3 UserBadge records (if challenged finalized)
- ✅ All foreign key relationships intact

## 🎯 Success Criteria

✅ All routes accessible and functional
✅ Forms submit without errors
✅ Database persists all data
✅ Voting works via AJAX
✅ Badges auto-awarded on finalize
✅ Admin controls fully operational
✅ Creator profiles display correctly
✅ Hall of Fame shows past winners
✅ No console errors
✅ No database errors

---

**Ready to test! 🚀**
