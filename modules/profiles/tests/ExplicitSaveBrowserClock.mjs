// Inject only the opt-in surface clock. Application/bootstrap/network timers remain real.
export const explicitSaveClockSource = `
  window.__explicitSaveClock={now:0,id:0,tasks:new Map(),
    setTimeout(fn,delay){this.tasks.set(++this.id,{at:this.now+delay,fn});return this.id},
    clearTimeout(id){this.tasks.delete(id)},
    advance(ms){this.now+=ms;for(const [id,t] of [...this.tasks])if(t.at<=this.now){this.tasks.delete(id);t.fn()}}
  };
  let explicitSaveFactory;
  Object.defineProperty(window,'mxmedCreateExplicitSaveSurface',{configurable:true,
    get(){return explicitSaveFactory},
    set(value){explicitSaveFactory=options=>{
      window.__explicitSaveOptions=options;
      return value({...options,clock:window.__explicitSaveClock});
    }}
  });
`;
