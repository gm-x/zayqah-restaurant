(() => {
 const messages=window.ZAYQAH_MESSAGES||{};
 window.zqT=(key,params={})=>{
  let value=messages[key]||key;
  for(const [name,replacement] of Object.entries(params))value=value.split('{'+name+'}').join(String(replacement));
  return value;
 };
})();
