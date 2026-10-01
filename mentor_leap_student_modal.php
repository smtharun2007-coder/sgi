<div id="leapStuModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:10000;align-items:center;justify-content:center;padding:16px;" onclick="if(event.target===this)leapStuModalClose()">
  <div style="background:#fff;border-radius:20px;max-width:640px;width:100%;max-height:88vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div style="position:sticky;top:0;background:linear-gradient(135deg,#1a1a2e,#8e44ad);padding:18px 24px;display:flex;justify-content:space-between;align-items:center;border-radius:20px 20px 0 0;">
      <div id="leapStuModalTitle" style="color:#fff;font-weight:700;font-size:16px;">Student Details</div>
      <button type="button" onclick="leapStuModalClose()" style="background:rgba(255,255,255,0.2);border:none;color:#fff;font-size:20px;cursor:pointer;width:32px;height:32px;border-radius:50%;line-height:1;">&times;</button>
    </div>
    <div id="leapStuModalBody" style="padding:24px;"><p style="color:#888;">Loading...</p></div>
  </div>
</div>
<script>
function leapStuModalClose(){var mm=document.getElementById('leapStuModal');if(mm)mm.style.display='none';document.body.style.overflow='';}
function leapStuEsc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function openLeapStudent(roll){
  var modal=document.getElementById('leapStuModal');
  var body=document.getElementById('leapStuModalBody');
  var title=document.getElementById('leapStuModalTitle');
  title.textContent='Loading...';
  body.innerHTML='<p style="color:#888;">Loading student details...</p>';
  modal.style.display='flex';document.body.style.overflow='hidden';
  fetch('mentor_student_detail.php?roll='+encodeURIComponent(roll))
    .then(function(r){return r.json();}).then(function(d){
      if(!d||d.status!=='success'){title.textContent='Error';body.innerHTML='<p style="color:#e94560;">'+leapStuEsc((d&&d.message)||'Failed to load.')+'</p>';return;}
      var s=d.student;title.textContent=s.name+' ('+s.roll+')';
      var pct=d.attendance.pct;var pc=pct===null?'#aaa':(pct>=75?'#28a745':'#e94560');
      var h='<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">'
        +'<div style="background:#f8f9fa;border-radius:10px;padding:12px;"><div style="font-size:11px;color:#888;">ROLL</div><div style="font-weight:700;color:#1a1a2e;overflow-wrap:anywhere;">'+leapStuEsc(s.roll)+'</div></div>'
        +'<div style="background:#f8f9fa;border-radius:10px;padding:12px;"><div style="font-size:11px;color:#888;">BATCH</div><div style="font-weight:700;color:#1a1a2e;">'+leapStuEsc(s.batch||'-')+'</div></div>'
        +'<div style="background:#f8f9fa;border-radius:10px;padding:12px;"><div style="font-size:11px;color:#888;">DEPT</div><div style="font-weight:700;color:#1a1a2e;">'+leapStuEsc(s.dept||'-')+'</div></div>'
        +'<div style="background:#f8f9fa;border-radius:10px;padding:12px;"><div style="font-size:11px;color:#888;">PACC</div><div style="font-weight:700;color:#1a1a2e;">'+leapStuEsc(s.pacc||'Not Assigned')+'</div></div></div>';
      h+='<div style="border:1px solid #f0f2f5;border-radius:12px;padding:14px 16px;margin-bottom:12px;"><div style="font-weight:700;color:#1a1a2e;margin-bottom:8px;">Training Attendance - <span style="color:'+pc+';">'+(pct===null?'-':pct+'%')+'</span></div><div style="font-size:13px;color:#666;">'+d.attendance.present+' present / '+d.attendance.conducted+' conducted'+(d.attendance.suspended?' - '+d.attendance.suspended+' suspended':'')+'</div></div>';
      h+='<div style="border:1px solid #f0f2f5;border-radius:12px;padding:14px 16px;margin-bottom:12px;"><div style="font-weight:700;color:#1a1a2e;margin-bottom:8px;">Tests and Results'+(d.results.avg!==null?' - '+d.results.avg+'% avg':'')+'</div>';
      if(!d.results.items.length){h+='<div style="font-size:13px;color:#aaa;">No published results yet.</div>';}
      else{d.results.items.forEach(function(r){h+='<div style="display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid #f5f5f5;"><span>'+leapStuEsc(r.title)+'</span><strong>'+r.marks+'/'+r.max+'</strong></div>';});}
      h+='</div>';
      h+='<div style="border:1px solid #f0f2f5;border-radius:12px;padding:14px 16px;margin-bottom:12px;"><div style="font-weight:700;color:#1a1a2e;margin-bottom:8px;">Recent Sessions</div>';
      if(!d.sessions.length){h+='<div style="font-size:13px;color:#aaa;">No sessions yet.</div>';}
      else{d.sessions.forEach(function(x){var c=x.att==='PRESENT'?'#28a745':(x.att==='ABSENT'?'#e94560':'#888');h+='<div style="display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid #f5f5f5;"><span>'+leapStuEsc(x.title)+'</span><span style="font-weight:700;color:'+c+';">'+leapStuEsc(x.att)+'</span></div>';});}
      h+='</div>';
      if(d.profiles&&(d.profiles.leetcode||d.profiles.hackerrank)){h+='<div style="display:flex;gap:10px;flex-wrap:wrap;">';if(d.profiles.leetcode)h+='<a href="'+leapStuEsc(d.profiles.leetcode)+'" target="_blank" rel="noopener" style="padding:8px 14px;background:#f5a623;color:#fff;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;">LeetCode</a>';if(d.profiles.hackerrank)h+='<a href="'+leapStuEsc(d.profiles.hackerrank)+'" target="_blank" rel="noopener" style="padding:8px 14px;background:#2ec866;color:#fff;border-radius:8px;font-size:12px;font-weight:700;text-decoration:none;">HackerRank</a>';h+='</div>';}
      body.innerHTML=h;
    }).catch(function(){title.textContent='Error';body.innerHTML='<p style="color:#e94560;">Failed to load.</p>';});
}
document.addEventListener('keydown',function(e){if(e.key==='Escape'){var mm=document.getElementById('leapStuModal');if(mm&&mm.style.display==='flex')leapStuModalClose();}});
</script>
