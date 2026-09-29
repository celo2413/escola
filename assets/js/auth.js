'use strict';
const Auth=(()=>{
 const labels={diretor:'Diretor',coordenador:'Coordenação pedagógica',professor:'Professor',aluno:'Aluno',responsavel:'Responsável'};
 const menus={diretor:['dashboard','students','teachers','coordinators','classes','subjects','enrollments','grades','attendance','diary','contents','activities','occurrences','time','announcements','notifications','users','reports','audit','years','terms','links','schedules','evaluations','settings','profile'],coordenador:['dashboard','students','classes','teachers','attendance','grades','activities','occurrences','announcements','reports','subjects','years','terms','links','schedules','profile'],professor:['dashboard','time','agenda','classes','contents','diary','grades','evaluations','activities','occurrences','announcements','profile'],aluno:['dashboard','reportcard','mygrades','attendance','absences','activities','myclass','teachers','occurrences','announcements','profile'],responsavel:['dashboard','student','reportcard','mygrades','attendance','absences','activities','occurrences','announcements','profile']};
 const user=()=>DB.data.user;
 const manager=()=>['diretor','coordenador'].includes(user()?.role);
 const personal=()=>['aluno','responsavel'].includes(user()?.role);
 async function login(email,password,remember){await API.request('/auth/login','POST',{email,password,remember});await DB.refresh();return true;}
 async function logout(){try{await API.request('/auth/logout','POST',{});}finally{API.clear();DB.clear();}}
 const scope=type=>user()?DB.data[type]||[]:[];
 function classIds(){if(manager())return DB.data.classes.map(c=>c.id);if(user()?.role==='professor')return DB.data.classes.map(c=>c.id);return DB.data.students.filter(s=>user()?.studentIds.includes(s.id)).map(s=>s.classId);}
 function canWrite(type,r){const u=user();if(!u)return false;if(u.role==='diretor')return type!=='students'||!!r;if(u.role==='coordenador')return ['classes','subjects','announcements','occurrences'].includes(type);if(u.role!=='professor'||!['grades','attendance','activities','occurrences','contents'].includes(type))return false;if(!r)return true;return classIds().includes(r.classId)&&(!r.teacherId||r.teacherId===u.teacherId)&&(!r.subjectId||DB.data.subjects.some(s=>s.id===r.subjectId));}
 return {labels,menus,manager,personal,scope,classIds,canWrite,login,logout,restore:user,get user(){return user();},canView:page=>!!user()&&(menus[user().role].includes(page)||page==='help'||page==='notifications'||page==='student'||page==='agenda'&&personal())};
})();
