</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const body=document.body;
const overlay=document.getElementById('adminOverlay');
const toggle=document.getElementById('adminMenuToggle');
if(toggle)toggle.addEventListener('click',()=>body.classList.toggle('sidebar-open'));
if(overlay)overlay.addEventListener('click',()=>body.classList.remove('sidebar-open'));
document.querySelectorAll('.money-input').forEach(input=>{
    const format=()=>{const value=input.value.replace(/\D/g,'');input.value=value?Number(value).toLocaleString('en-US'):'';};
    input.addEventListener('input',format);format();
});
</script>
</body>
</html>