const signUpButton=document.getElementById('signUpButton');
const signInButton=document.getElementById('signInButton');
const signInForm=document.getElementById('signIn');
const signUpForm=document.getElementById('signup');

// signUpButton.addEventListener('click',function(){
//     signInForm.style.display="none";
//     signUpForm.style.display="block";
// })
// signInButton.addEventListener('click', function(){
//     signInForm.style.display="block";
//     signUpForm.style.display="none";
// })

// show the password use the eye icon
document.querySelectorAll('.toggle-password').forEach(icon => { 
    icon.addEventListener('click', function(){
        const input=this.previousElementSibling;
        if(input.type=="password"){
            input.type='text';
            this.innerHTML='<i class="fas fa-eye-slash"></i>';
        }else{
            input.type="password";
            this.innerHTML='<i class="fas fa-eye"></i>';
            }
        });
});